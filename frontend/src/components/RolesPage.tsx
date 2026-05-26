import {
  AlertDialog,
  Badge,
  Box,
  Button,
  Card,
  Dialog,
  Flex,
  Heading,
  IconButton,
  Select,
  Table,
  Text,
  TextField,
  Tooltip,
} from '@radix-ui/themes';
import { useState } from 'react';

import {
  useAdminRoles,
  useCloneRole,
  useCreateRole,
  useDeleteRole,
  useRestoreRole,
} from '../hooks/useRbac.js';

/**
 * Strings the page renders. Hosts pass an overlay to translate
 * without touching the component. Defaults are English — Portuguese
 * hosts pass labels: { ... } prop.
 */
export interface RolesPageLabels {
  title: string;
  createButton: string;
  cloneButton: string;
  deleteButton: string;
  restoreButton: string;
  createModalTitle: string;
  cloneModalTitle: string;
  deleteConfirmTitle: string;
  deleteConfirmDescription: string;
  systemBadge: string;
  trashedBadge: string;
  loading: string;
  empty: string;
  showTrashed: string;
  hideTrashed: string;
  paginationLabel: (offset: number, limit: number, total: number) => string;
  fields: {
    name: string;
    displayName: string;
    guardName: string;
    level: string;
  };
}

const DEFAULT_LABELS: RolesPageLabels = {
  title: 'Roles',
  createButton: 'New role',
  cloneButton: 'Clone',
  deleteButton: 'Delete',
  restoreButton: 'Restore',
  createModalTitle: 'New role',
  cloneModalTitle: 'Clone role',
  deleteConfirmTitle: 'Delete role?',
  deleteConfirmDescription:
    'The role becomes soft-deleted. Users assigned to it lose access until it is restored.',
  systemBadge: 'system',
  trashedBadge: 'trashed',
  loading: 'Loading…',
  empty: 'No roles yet. Click "New role" to start.',
  showTrashed: 'Show deleted',
  hideTrashed: 'Hide deleted',
  paginationLabel: (offset, limit, total) =>
    `${offset + 1}–${Math.min(offset + limit, total)} of ${total}`,
  fields: {
    name: 'Slug',
    displayName: 'Display name',
    guardName: 'Guard',
    level: 'Level',
  },
};

export interface RolesPageProps {
  /** Page size; defaults to 25. */
  limit?: number;
  /** Localized strings. Falls back to English defaults per key. */
  labels?: Partial<RolesPageLabels>;
  /** Optional callback when the user clicks a row (e.g. host navigates to edit screen). */
  onRoleSelect?: (roleId: string) => void;
}

interface RoleRow {
  id: string;
  name: string;
  display_name: string | null;
  guard_name: string;
  level: number;
  is_system: boolean;
  deleted_at?: string | null;
}

export function RolesPage({ limit = 25, labels: labelOverrides, onRoleSelect }: RolesPageProps) {
  const labels: RolesPageLabels = { ...DEFAULT_LABELS, ...labelOverrides, fields: { ...DEFAULT_LABELS.fields, ...labelOverrides?.fields } };

  const [offset, setOffset] = useState(0);
  const [showTrashed, setShowTrashed] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [cloneSource, setCloneSource] = useState<RoleRow | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<RoleRow | null>(null);

  const query = useAdminRoles({ limit, offset });
  const createRole = useCreateRole();
  const cloneRole = useCloneRole();
  const deleteRole = useDeleteRole();
  const restoreRole = useRestoreRole();

  // GET /roles in the spec doesn't pin a content schema, so the type
  // resolves to `unknown`. Cast through `unknown` to apply the
  // documented envelope shape locally.
  const envelope = query.data as unknown as
    | { data?: RoleRow[]; meta?: { total?: number } }
    | undefined;
  const rows: RoleRow[] = Array.isArray(envelope?.data) ? envelope.data : [];
  const total = envelope?.meta?.total ?? rows.length;

  const visible = showTrashed ? rows : rows.filter((r) => !r.deleted_at);

  return (
    <Box>
      <Flex justify="between" align="center" mb="4">
        <Heading size="6">{labels.title}</Heading>
        <Flex gap="2" align="center">
          <Button variant="soft" onClick={() => setShowTrashed((s) => !s)}>
            {showTrashed ? labels.hideTrashed : labels.showTrashed}
          </Button>
          <Button onClick={() => setCreateOpen(true)}>{labels.createButton}</Button>
        </Flex>
      </Flex>

      <Card>
        {query.isLoading ? (
          <Text>{labels.loading}</Text>
        ) : visible.length === 0 ? (
          <Text color="gray">{labels.empty}</Text>
        ) : (
          <Table.Root variant="surface">
            <Table.Header>
              <Table.Row>
                <Table.ColumnHeaderCell>{labels.fields.name}</Table.ColumnHeaderCell>
                <Table.ColumnHeaderCell>{labels.fields.displayName}</Table.ColumnHeaderCell>
                <Table.ColumnHeaderCell>{labels.fields.guardName}</Table.ColumnHeaderCell>
                <Table.ColumnHeaderCell>{labels.fields.level}</Table.ColumnHeaderCell>
                <Table.ColumnHeaderCell width="200px"></Table.ColumnHeaderCell>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {visible.map((role) => (
                <Table.Row key={role.id} style={{ cursor: onRoleSelect ? 'pointer' : 'default' }}>
                  <Table.Cell onClick={() => onRoleSelect?.(role.id)}>
                    <Flex gap="2" align="center">
                      <Text weight="medium">{role.name}</Text>
                      {role.is_system && <Badge color="amber">{labels.systemBadge}</Badge>}
                      {role.deleted_at && <Badge color="gray">{labels.trashedBadge}</Badge>}
                    </Flex>
                  </Table.Cell>
                  <Table.Cell>{role.display_name ?? '—'}</Table.Cell>
                  <Table.Cell>{role.guard_name}</Table.Cell>
                  <Table.Cell>{role.level}</Table.Cell>
                  <Table.Cell>
                    <Flex gap="2" justify="end">
                      {role.deleted_at ? (
                        <Tooltip content={labels.restoreButton}>
                          <IconButton
                            size="1"
                            variant="soft"
                            onClick={() => restoreRole.mutate(role.id)}
                          >
                            ⟲
                          </IconButton>
                        </Tooltip>
                      ) : (
                        <>
                          <Tooltip content={labels.cloneButton}>
                            <IconButton
                              size="1"
                              variant="soft"
                              onClick={() => setCloneSource(role)}
                            >
                              ⎘
                            </IconButton>
                          </Tooltip>
                          <Tooltip content={role.is_system ? labels.systemBadge : labels.deleteButton}>
                            <IconButton
                              size="1"
                              variant="soft"
                              color="red"
                              disabled={role.is_system}
                              onClick={() => setDeleteTarget(role)}
                            >
                              ✕
                            </IconButton>
                          </Tooltip>
                        </>
                      )}
                    </Flex>
                  </Table.Cell>
                </Table.Row>
              ))}
            </Table.Body>
          </Table.Root>
        )}

        {total > limit && (
          <Flex justify="between" align="center" mt="3">
            <Text size="2" color="gray">
              {labels.paginationLabel(offset, limit, total)}
            </Text>
            <Flex gap="2">
              <Button
                variant="soft"
                disabled={offset === 0}
                onClick={() => setOffset((o) => Math.max(0, o - limit))}
              >
                ← Prev
              </Button>
              <Button
                variant="soft"
                disabled={offset + limit >= total}
                onClick={() => setOffset((o) => o + limit)}
              >
                Next →
              </Button>
            </Flex>
          </Flex>
        )}
      </Card>

      <CreateRoleDialog
        open={createOpen}
        onOpenChange={setCreateOpen}
        labels={labels}
        onSubmit={async (payload) => {
          await createRole.mutateAsync(payload);
          setCreateOpen(false);
        }}
      />

      <CloneRoleDialog
        source={cloneSource}
        onOpenChange={(open) => !open && setCloneSource(null)}
        labels={labels}
        onSubmit={async (payload) => {
          if (cloneSource) {
            await cloneRole.mutateAsync({ sourceId: cloneSource.id, payload });
            setCloneSource(null);
          }
        }}
      />

      <AlertDialog.Root
        open={deleteTarget !== null}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
      >
        <AlertDialog.Content maxWidth="450px">
          <AlertDialog.Title>{labels.deleteConfirmTitle}</AlertDialog.Title>
          <AlertDialog.Description size="2">
            {labels.deleteConfirmDescription}
          </AlertDialog.Description>
          <Flex gap="3" mt="4" justify="end">
            <AlertDialog.Cancel>
              <Button variant="soft" color="gray">
                Cancel
              </Button>
            </AlertDialog.Cancel>
            <AlertDialog.Action>
              <Button
                color="red"
                onClick={() => {
                  if (deleteTarget) {
                    deleteRole.mutate(deleteTarget.id);
                    setDeleteTarget(null);
                  }
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

interface CreatePayload {
  name: string;
  display_name?: string;
  guard_name: string;
  level: number;
}

function CreateRoleDialog({
  open,
  onOpenChange,
  labels,
  onSubmit,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  labels: RolesPageLabels;
  onSubmit: (payload: CreatePayload) => Promise<void>;
}) {
  const [name, setName] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [guard, setGuard] = useState('admin');
  const [level, setLevel] = useState(0);

  const submit = async () => {
    await onSubmit({
      name: name.trim(),
      display_name: displayName.trim() || undefined,
      guard_name: guard,
      level: Number.isFinite(level) ? level : 0,
    });
    setName('');
    setDisplayName('');
    setLevel(0);
  };

  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange}>
      <Dialog.Content maxWidth="450px">
        <Dialog.Title>{labels.createModalTitle}</Dialog.Title>
        <Flex direction="column" gap="3" mt="3">
          <label>
            <Text size="2" weight="medium">
              {labels.fields.name}
            </Text>
            <TextField.Root
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="editor"
              required
            />
          </label>
          <label>
            <Text size="2" weight="medium">
              {labels.fields.displayName}
            </Text>
            <TextField.Root
              value={displayName}
              onChange={(e) => setDisplayName(e.target.value)}
              placeholder="Editor"
            />
          </label>
          <label>
            <Text size="2" weight="medium">
              {labels.fields.guardName}
            </Text>
            <Select.Root value={guard} onValueChange={setGuard}>
              <Select.Trigger />
              <Select.Content>
                <Select.Item value="admin">admin</Select.Item>
                <Select.Item value="web">web</Select.Item>
                <Select.Item value="api">api</Select.Item>
              </Select.Content>
            </Select.Root>
          </label>
          <label>
            <Text size="2" weight="medium">
              {labels.fields.level}
            </Text>
            <TextField.Root
              type="number"
              value={level}
              onChange={(e) => setLevel(parseInt(e.target.value, 10) || 0)}
            />
          </label>
        </Flex>
        <Flex gap="3" mt="4" justify="end">
          <Dialog.Close>
            <Button variant="soft" color="gray">
              Cancel
            </Button>
          </Dialog.Close>
          <Button onClick={submit} disabled={!name.trim()}>
            {labels.createModalTitle}
          </Button>
        </Flex>
      </Dialog.Content>
    </Dialog.Root>
  );
}

function CloneRoleDialog({
  source,
  onOpenChange,
  labels,
  onSubmit,
}: {
  source: RoleRow | null;
  onOpenChange: (open: boolean) => void;
  labels: RolesPageLabels;
  onSubmit: (payload: { name: string; display_name?: string }) => Promise<void>;
}) {
  const [name, setName] = useState('');

  const submit = async () => {
    await onSubmit({ name: name.trim() });
    setName('');
  };

  return (
    <Dialog.Root open={source !== null} onOpenChange={onOpenChange}>
      <Dialog.Content maxWidth="450px">
        <Dialog.Title>{labels.cloneModalTitle}</Dialog.Title>
        {source && (
          <Text size="2" color="gray">
            Cloning <strong>{source.name}</strong> — all module bindings will be copied.
          </Text>
        )}
        <Flex direction="column" gap="3" mt="3">
          <label>
            <Text size="2" weight="medium">
              {labels.fields.name} (new)
            </Text>
            <TextField.Root
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder={source ? `${source.name}_copy` : ''}
              required
            />
          </label>
        </Flex>
        <Flex gap="3" mt="4" justify="end">
          <Dialog.Close>
            <Button variant="soft" color="gray">
              Cancel
            </Button>
          </Dialog.Close>
          <Button onClick={submit} disabled={!name.trim()}>
            {labels.cloneButton}
          </Button>
        </Flex>
      </Dialog.Content>
    </Dialog.Root>
  );
}
