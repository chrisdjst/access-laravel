import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  arrayMove,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
  AlertDialog,
  Badge,
  Box,
  Button,
  Card,
  Checkbox,
  Dialog,
  Flex,
  Heading,
  IconButton,
  Switch,
  Text,
  TextField,
  Tooltip,
} from '@radix-ui/themes';
import { useEffect, useMemo, useState } from 'react';

import type { components } from '../api/rbac.js';
import {
  useAdminModules,
  useBulkDeleteModules,
  useCreateModule,
  useDeleteModule,
  useUpdateModule,
  type CreateModulePayload,
} from '../hooks/useRbac.js';

// The spec marks every Module field as optional (default openapi-typescript
// inference). At runtime the API always returns id + slug populated, so we
// narrow locally to keep the component code ergonomic.
type Module = components['schemas']['Module'] & { id: string; slug: string };

export interface ModulesTreeEditorLabels {
  title: string;
  addRootButton: string;
  saveButton: string;
  cancelButton: string;
  addChildButton: string;
  deleteButton: string;
  editButton: string;
  bulkDeleteButton: string;
  selectedCount: (n: number) => string;
  loading: string;
  empty: string;
  deleteConfirmTitle: string;
  deleteConfirmDescription: string;
  createModalTitle: string;
  editModalTitle: string;
  inactiveBadge: string;
  fields: {
    slug: string;
    name: string;
    icon: string;
    sortOrder: string;
    isActive: string;
  };
}

const DEFAULT_LABELS: ModulesTreeEditorLabels = {
  title: 'Modules',
  addRootButton: 'Add module',
  saveButton: 'Save',
  cancelButton: 'Cancel',
  addChildButton: 'Add child',
  deleteButton: 'Delete',
  editButton: 'Edit',
  bulkDeleteButton: 'Delete selected',
  selectedCount: (n) => `${n} selected`,
  loading: 'Loading modules…',
  empty: 'No modules yet. Click "Add module" to create the first one.',
  deleteConfirmTitle: 'Delete module?',
  deleteConfirmDescription:
    'Roles that referenced this module will lose access to it. This can be undone manually.',
  createModalTitle: 'New module',
  editModalTitle: 'Edit module',
  inactiveBadge: 'inactive',
  fields: {
    slug: 'Slug',
    name: 'Name',
    icon: 'Icon',
    sortOrder: 'Sort order',
    isActive: 'Active',
  },
};

export interface ModulesTreeEditorProps {
  labels?: Partial<ModulesTreeEditorLabels>;
  onModuleSelect?: (moduleId: string) => void;
}

function mergeLabels(partial?: Partial<ModulesTreeEditorLabels>): ModulesTreeEditorLabels {
  return {
    ...DEFAULT_LABELS,
    ...partial,
    fields: { ...DEFAULT_LABELS.fields, ...(partial?.fields ?? {}) },
  };
}

interface TreeNode {
  module: Module;
  children: TreeNode[];
}

function buildTree(modules: Module[]): TreeNode[] {
  const byParent = new Map<string | null, Module[]>();
  for (const m of modules) {
    const parentKey = (m.root_module_id ?? null) as string | null;
    const arr = byParent.get(parentKey) ?? [];
    arr.push(m);
    byParent.set(parentKey, arr);
  }
  for (const arr of byParent.values()) {
    arr.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
  }
  const build = (parentId: string | null): TreeNode[] =>
    (byParent.get(parentId) ?? []).map((module) => ({
      module,
      children: build(module.id),
    }));

  return build(null);
}

export function ModulesTreeEditor({ labels: labelsProp, onModuleSelect }: ModulesTreeEditorProps) {
  const labels = mergeLabels(labelsProp);
  const query = useAdminModules();
  const updateModule = useUpdateModule();
  const createModule = useCreateModule();
  const deleteModule = useDeleteModule();
  const bulkDeleteModules = useBulkDeleteModules();

  const envelope = query.data as unknown as { data?: Module[] } | undefined;
  const modules = useMemo<Module[]>(() => (envelope?.data ?? []) as Module[], [envelope]);
  const tree = useMemo(() => buildTree(modules), [modules]);

  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [editing, setEditing] = useState<Module | null>(null);
  const [creatingUnder, setCreatingUnder] = useState<{ parentId: string | null } | null>(null);
  const [pendingDelete, setPendingDelete] = useState<Module | null>(null);
  const [pendingBulkDelete, setPendingBulkDelete] = useState(false);

  useEffect(() => {
    // Drop selections that no longer exist after a refetch.
    setSelectedIds((prev) => {
      const next = new Set<string>();
      const known = new Set(modules.map((m) => m.id));
      for (const id of prev) {
        if (known.has(id)) {
          next.add(id);
        }
      }
      if (next.size === prev.size) return prev;

      return next;
    });
  }, [modules]);

  const toggle = (id: string) =>
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);

      return next;
    });

  if (query.isLoading) {
    return (
      <Box p="4">
        <Text>{labels.loading}</Text>
      </Box>
    );
  }

  return (
    <Box p="4">
      <Flex justify="between" align="center" mb="4">
        <Heading size="6">{labels.title}</Heading>
        <Flex gap="2">
          {selectedIds.size > 0 && (
            <>
              <Text size="2" color="gray" style={{ alignSelf: 'center' }}>
                {labels.selectedCount(selectedIds.size)}
              </Text>
              <Button color="red" variant="soft" onClick={() => setPendingBulkDelete(true)}>
                {labels.bulkDeleteButton}
              </Button>
            </>
          )}
          <Button onClick={() => setCreatingUnder({ parentId: null })}>{labels.addRootButton}</Button>
        </Flex>
      </Flex>

      {tree.length === 0 ? (
        <Card>
          <Box p="4">
            <Text>{labels.empty}</Text>
          </Box>
        </Card>
      ) : (
        <Card>
          <Box p="2">
            <TreeLevel
              nodes={tree}
              parentId={null}
              depth={0}
              labels={labels}
              selectedIds={selectedIds}
              onToggleSelect={toggle}
              onEdit={setEditing}
              onAddChild={(parentId) => setCreatingUnder({ parentId })}
              onDelete={setPendingDelete}
              onReorder={(_parentId, reorderedIds) => {
                reorderedIds.forEach((id, index) => {
                  updateModule.mutate({ id, payload: { sort_order: index } });
                });
              }}
              onSelectModule={onModuleSelect}
            />
          </Box>
        </Card>
      )}

      {editing && (
        <ModuleEditorDialog
          module={editing}
          labels={labels}
          onClose={() => setEditing(null)}
          onSave={(payload) => {
            updateModule.mutate(
              { id: editing.id, payload },
              { onSuccess: () => setEditing(null) },
            );
          }}
        />
      )}

      {creatingUnder && (
        <ModuleCreateDialog
          parentId={creatingUnder.parentId}
          labels={labels}
          onClose={() => setCreatingUnder(null)}
          onSave={(payload) => {
            createModule.mutate(payload, { onSuccess: () => setCreatingUnder(null) });
          }}
        />
      )}

      <AlertDialog.Root open={!!pendingDelete} onOpenChange={(o) => !o && setPendingDelete(null)}>
        <AlertDialog.Content>
          <AlertDialog.Title>{labels.deleteConfirmTitle}</AlertDialog.Title>
          <AlertDialog.Description size="2">
            {labels.deleteConfirmDescription}
          </AlertDialog.Description>
          <Flex gap="3" mt="4" justify="end">
            <AlertDialog.Cancel>
              <Button variant="soft" color="gray">
                {labels.cancelButton}
              </Button>
            </AlertDialog.Cancel>
            <AlertDialog.Action>
              <Button
                color="red"
                onClick={() => {
                  if (pendingDelete) deleteModule.mutate(pendingDelete.id);
                  setPendingDelete(null);
                }}
              >
                {labels.deleteButton}
              </Button>
            </AlertDialog.Action>
          </Flex>
        </AlertDialog.Content>
      </AlertDialog.Root>

      <AlertDialog.Root open={pendingBulkDelete} onOpenChange={setPendingBulkDelete}>
        <AlertDialog.Content>
          <AlertDialog.Title>{labels.deleteConfirmTitle}</AlertDialog.Title>
          <AlertDialog.Description size="2">
            {labels.deleteConfirmDescription}
          </AlertDialog.Description>
          <Flex gap="3" mt="4" justify="end">
            <AlertDialog.Cancel>
              <Button variant="soft" color="gray">
                {labels.cancelButton}
              </Button>
            </AlertDialog.Cancel>
            <AlertDialog.Action>
              <Button
                color="red"
                onClick={() => {
                  bulkDeleteModules.mutate(
                    { ids: Array.from(selectedIds) },
                    {
                      onSuccess: () => {
                        setSelectedIds(new Set());
                        setPendingBulkDelete(false);
                      },
                    },
                  );
                }}
              >
                {labels.deleteButton}
              </Button>
            </AlertDialog.Action>
          </Flex>
        </AlertDialog.Content>
      </AlertDialog.Root>
    </Box>
  );
}

interface TreeLevelProps {
  nodes: TreeNode[];
  parentId: string | null;
  depth: number;
  labels: ModulesTreeEditorLabels;
  selectedIds: Set<string>;
  onToggleSelect: (id: string) => void;
  onEdit: (module: Module) => void;
  onAddChild: (parentId: string) => void;
  onDelete: (module: Module) => void;
  onReorder: (parentId: string | null, reorderedIds: string[]) => void;
  onSelectModule?: (id: string) => void;
}

function TreeLevel(props: TreeLevelProps) {
  const { nodes, parentId, onReorder } = props;
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const oldIndex = nodes.findIndex((n) => n.module.id === active.id);
    const newIndex = nodes.findIndex((n) => n.module.id === over.id);
    if (oldIndex < 0 || newIndex < 0) return;
    const reordered = arrayMove(nodes, oldIndex, newIndex).map((n) => n.module.id);
    onReorder(parentId, reordered);
  };

  return (
    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
      <SortableContext
        items={nodes.map((n) => n.module.id)}
        strategy={verticalListSortingStrategy}
      >
        <Flex direction="column" gap="1">
          {nodes.map((node) => (
            <TreeRow key={node.module.id} node={node} {...props} />
          ))}
        </Flex>
      </SortableContext>
    </DndContext>
  );
}

interface TreeRowProps extends TreeLevelProps {
  node: TreeNode;
}

function TreeRow({
  node,
  depth,
  labels,
  selectedIds,
  onToggleSelect,
  onEdit,
  onAddChild,
  onDelete,
  onReorder,
  onSelectModule,
}: TreeRowProps) {
  const { module: m, children } = node;
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: m.id,
  });
  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };
  const checked = selectedIds.has(m.id);

  return (
    <Box style={style} ref={setNodeRef}>
      <Flex
        align="center"
        gap="2"
        py="2"
        px="2"
        style={{
          paddingLeft: `${depth * 24 + 8}px`,
          borderRadius: 6,
          background: checked ? 'var(--accent-3)' : undefined,
        }}
      >
        <span
          {...listeners}
          {...attributes}
          aria-label="Drag to reorder"
          style={{ cursor: 'grab', userSelect: 'none', padding: '0 4px' }}
        >
          ⠿
        </span>
        <Checkbox checked={checked} onCheckedChange={() => onToggleSelect(m.id)} />
        <Box style={{ flex: 1, minWidth: 0, cursor: onSelectModule ? 'pointer' : 'default' }} onClick={() => onSelectModule?.(m.id)}>
          <Flex align="center" gap="2">
            <Text weight="medium">{m.name ?? m.slug}</Text>
            <Text color="gray" size="1">
              {m.slug}
            </Text>
            {m.is_active === false && <Badge color="gray">{labels.inactiveBadge}</Badge>}
          </Flex>
        </Box>
        <Tooltip content={labels.addChildButton}>
          <IconButton variant="ghost" size="1" onClick={() => onAddChild(m.id)}>
            +
          </IconButton>
        </Tooltip>
        <Tooltip content={labels.editButton}>
          <IconButton variant="ghost" size="1" onClick={() => onEdit(m)}>
            ✎
          </IconButton>
        </Tooltip>
        <Tooltip content={labels.deleteButton}>
          <IconButton variant="ghost" color="red" size="1" onClick={() => onDelete(m)}>
            ✕
          </IconButton>
        </Tooltip>
      </Flex>
      {children.length > 0 && (
        <TreeLevel
          labels={labels}
          selectedIds={selectedIds}
          onToggleSelect={onToggleSelect}
          onEdit={onEdit}
          onAddChild={onAddChild}
          onDelete={onDelete}
          onReorder={onReorder}
          onSelectModule={onSelectModule}
          nodes={children}
          parentId={m.id}
          depth={depth + 1}
        />
      )}
    </Box>
  );
}

interface EditorState {
  name: string;
  icon: string;
  is_active: boolean;
}

function ModuleEditorDialog({
  module: m,
  labels,
  onClose,
  onSave,
}: {
  module: Module;
  labels: ModulesTreeEditorLabels;
  onClose: () => void;
  onSave: (payload: { name: string; icon: string | null; is_active: boolean }) => void;
}) {
  const [state, setState] = useState<EditorState>({
    name: m.name ?? '',
    icon: m.icon ?? '',
    is_active: m.is_active ?? true,
  });

  return (
    <Dialog.Root open onOpenChange={(o) => !o && onClose()}>
      <Dialog.Content style={{ maxWidth: 480 }}>
        <Dialog.Title>{labels.editModalTitle}</Dialog.Title>
        <Flex direction="column" gap="3" mt="4">
          <label>
            <Text as="div" size="2" mb="1" weight="bold">
              {labels.fields.name}
            </Text>
            <TextField.Root
              value={state.name}
              onChange={(e) => setState({ ...state, name: e.target.value })}
            />
          </label>
          <label>
            <Text as="div" size="2" mb="1" weight="bold">
              {labels.fields.icon}
            </Text>
            <TextField.Root
              value={state.icon}
              onChange={(e) => setState({ ...state, icon: e.target.value })}
            />
          </label>
          <Flex align="center" gap="2">
            <Switch
              checked={state.is_active}
              onCheckedChange={(v) => setState({ ...state, is_active: v })}
            />
            <Text size="2">{labels.fields.isActive}</Text>
          </Flex>
        </Flex>
        <Flex gap="3" mt="4" justify="end">
          <Dialog.Close>
            <Button variant="soft" color="gray">
              {labels.cancelButton}
            </Button>
          </Dialog.Close>
          <Button
            onClick={() =>
              onSave({
                name: state.name,
                icon: state.icon || null,
                is_active: state.is_active,
              })
            }
          >
            {labels.saveButton}
          </Button>
        </Flex>
      </Dialog.Content>
    </Dialog.Root>
  );
}

function ModuleCreateDialog({
  parentId,
  labels,
  onClose,
  onSave,
}: {
  parentId: string | null;
  labels: ModulesTreeEditorLabels;
  onClose: () => void;
  onSave: (payload: CreateModulePayload) => void;
}) {
  const [slug, setSlug] = useState('');
  const [name, setName] = useState('');
  const [icon, setIcon] = useState('');

  return (
    <Dialog.Root open onOpenChange={(o) => !o && onClose()}>
      <Dialog.Content style={{ maxWidth: 480 }}>
        <Dialog.Title>{labels.createModalTitle}</Dialog.Title>
        <Flex direction="column" gap="3" mt="4">
          <label>
            <Text as="div" size="2" mb="1" weight="bold">
              {labels.fields.slug}
            </Text>
            <TextField.Root value={slug} onChange={(e) => setSlug(e.target.value)} />
          </label>
          <label>
            <Text as="div" size="2" mb="1" weight="bold">
              {labels.fields.name}
            </Text>
            <TextField.Root value={name} onChange={(e) => setName(e.target.value)} />
          </label>
          <label>
            <Text as="div" size="2" mb="1" weight="bold">
              {labels.fields.icon}
            </Text>
            <TextField.Root value={icon} onChange={(e) => setIcon(e.target.value)} />
          </label>
        </Flex>
        <Flex gap="3" mt="4" justify="end">
          <Dialog.Close>
            <Button variant="soft" color="gray">
              {labels.cancelButton}
            </Button>
          </Dialog.Close>
          <Button
            disabled={!slug || !name}
            onClick={() =>
              onSave({
                slug,
                name,
                icon: icon || null,
                root_module_id: parentId,
                sort_order: 0,
                is_active: true,
                translations: {},
              } as CreateModulePayload)
            }
          >
            {labels.saveButton}
          </Button>
        </Flex>
      </Dialog.Content>
    </Dialog.Root>
  );
}
