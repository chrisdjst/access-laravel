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
  Switch,
  Table,
  Text,
  TextField,
  Tooltip,
} from '@radix-ui/themes';
import { useMemo, useState } from 'react';

import type { components } from '../api/rbac.js';
import {
  useAdminLanguages,
  useCreateLanguage,
  useDeleteLanguage,
  useSetDefaultLanguage,
  useUpdateLanguage,
} from '../hooks/useRbac.js';

type Language = components['schemas']['Language'] & { id: string; code: string };

export interface LanguagesAdminLabels {
  title: string;
  addButton: string;
  saveButton: string;
  cancelButton: string;
  editButton: string;
  deleteButton: string;
  setDefaultButton: string;
  defaultBadge: string;
  inactiveBadge: string;
  loading: string;
  empty: string;
  createModalTitle: string;
  editModalTitle: string;
  deleteConfirmTitle: string;
  deleteConfirmDescription: string;
  defaultConfirmTitle: string;
  defaultConfirmDescription: string;
  fields: {
    code: string;
    name: string;
    isActive: string;
  };
  columns: {
    code: string;
    name: string;
    status: string;
    actions: string;
  };
}

const DEFAULT_LABELS: LanguagesAdminLabels = {
  title: 'Languages',
  addButton: 'Add language',
  saveButton: 'Save',
  cancelButton: 'Cancel',
  editButton: 'Edit',
  deleteButton: 'Delete',
  setDefaultButton: 'Set as default',
  defaultBadge: 'default',
  inactiveBadge: 'inactive',
  loading: 'Loading languages…',
  empty: 'No languages yet. Add one to begin translating.',
  createModalTitle: 'New language',
  editModalTitle: 'Edit language',
  deleteConfirmTitle: 'Delete language?',
  deleteConfirmDescription:
    'Translations stored for this language remain in the database but will no longer be served. The default language cannot be deleted.',
  defaultConfirmTitle: 'Change default language?',
  defaultConfirmDescription:
    'Existing translations are not migrated. Users without their preferred language fall back to the default — make sure key strings are translated first.',
  fields: {
    code: 'Code (e.g. en, pt_BR)',
    name: 'Display name',
    isActive: 'Active',
  },
  columns: {
    code: 'Code',
    name: 'Name',
    status: 'Status',
    actions: 'Actions',
  },
};

export interface LanguagesAdminProps {
  labels?: Partial<LanguagesAdminLabels>;
}

function mergeLabels(partial?: Partial<LanguagesAdminLabels>): LanguagesAdminLabels {
  return {
    ...DEFAULT_LABELS,
    ...partial,
    fields: { ...DEFAULT_LABELS.fields, ...(partial?.fields ?? {}) },
    columns: { ...DEFAULT_LABELS.columns, ...(partial?.columns ?? {}) },
  };
}

export function LanguagesAdmin({ labels: labelsProp }: LanguagesAdminProps = {}) {
  const labels = mergeLabels(labelsProp);
  const query = useAdminLanguages();
  const createLanguage = useCreateLanguage();
  const updateLanguage = useUpdateLanguage();
  const deleteLanguage = useDeleteLanguage();
  const setDefaultLanguage = useSetDefaultLanguage();

  const envelope = query.data as unknown as { data?: Language[] } | undefined;
  const languages = useMemo<Language[]>(() => (envelope?.data ?? []) as Language[], [envelope]);
  const currentDefault = useMemo(() => languages.find((l) => l.is_default), [languages]);

  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<Language | null>(null);
  const [pendingDelete, setPendingDelete] = useState<Language | null>(null);
  const [pendingDefault, setPendingDefault] = useState<Language | null>(null);

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
        <Button onClick={() => setCreating(true)}>{labels.addButton}</Button>
      </Flex>

      {languages.length === 0 ? (
        <Card>
          <Box p="4">
            <Text>{labels.empty}</Text>
          </Box>
        </Card>
      ) : (
        <Card>
          <Table.Root variant="surface">
            <Table.Header>
              <Table.Row>
                <Table.ColumnHeaderCell>{labels.columns.code}</Table.ColumnHeaderCell>
                <Table.ColumnHeaderCell>{labels.columns.name}</Table.ColumnHeaderCell>
                <Table.ColumnHeaderCell>{labels.columns.status}</Table.ColumnHeaderCell>
                <Table.ColumnHeaderCell width="220px">
                  {labels.columns.actions}
                </Table.ColumnHeaderCell>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {languages.map((lang) => (
                <Table.Row key={lang.id}>
                  <Table.RowHeaderCell>
                    <Text style={{ fontFamily: 'monospace' }}>{lang.code}</Text>
                  </Table.RowHeaderCell>
                  <Table.Cell>
                    <Text weight="medium">{lang.name ?? lang.code}</Text>
                  </Table.Cell>
                  <Table.Cell>
                    <Flex gap="2" wrap="wrap">
                      {lang.is_default && <Badge color="indigo">{labels.defaultBadge}</Badge>}
                      {lang.is_active === false && (
                        <Badge color="gray">{labels.inactiveBadge}</Badge>
                      )}
                    </Flex>
                  </Table.Cell>
                  <Table.Cell>
                    <Flex gap="2">
                      {!lang.is_default && (
                        <Tooltip content={labels.setDefaultButton}>
                          <Button
                            size="1"
                            variant="soft"
                            onClick={() => setPendingDefault(lang)}
                          >
                            ★
                          </Button>
                        </Tooltip>
                      )}
                      <Tooltip content={labels.editButton}>
                        <IconButton size="1" variant="ghost" onClick={() => setEditing(lang)}>
                          ✎
                        </IconButton>
                      </Tooltip>
                      <Tooltip
                        content={
                          lang.is_default
                            ? `${labels.deleteButton} (${labels.defaultBadge})`
                            : labels.deleteButton
                        }
                      >
                        <IconButton
                          size="1"
                          variant="ghost"
                          color="red"
                          disabled={lang.is_default}
                          onClick={() => setPendingDelete(lang)}
                        >
                          ✕
                        </IconButton>
                      </Tooltip>
                    </Flex>
                  </Table.Cell>
                </Table.Row>
              ))}
            </Table.Body>
          </Table.Root>
        </Card>
      )}

      {creating && (
        <LanguageCreateDialog
          labels={labels}
          onClose={() => setCreating(false)}
          onSave={(payload) =>
            createLanguage.mutate(payload, { onSuccess: () => setCreating(false) })
          }
        />
      )}

      {editing && (
        <LanguageEditDialog
          language={editing}
          labels={labels}
          onClose={() => setEditing(null)}
          onSave={(payload) =>
            updateLanguage.mutate(
              { id: editing.id, payload },
              { onSuccess: () => setEditing(null) },
            )
          }
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
                  if (pendingDelete) deleteLanguage.mutate(pendingDelete.id);
                  setPendingDelete(null);
                }}
              >
                {labels.deleteButton}
              </Button>
            </AlertDialog.Action>
          </Flex>
        </AlertDialog.Content>
      </AlertDialog.Root>

      <AlertDialog.Root
        open={!!pendingDefault}
        onOpenChange={(o) => !o && setPendingDefault(null)}
      >
        <AlertDialog.Content>
          <AlertDialog.Title>{labels.defaultConfirmTitle}</AlertDialog.Title>
          <AlertDialog.Description size="2">
            {labels.defaultConfirmDescription}
            {currentDefault && pendingDefault && (
              <Text as="p" mt="2">
                <strong>{currentDefault.code}</strong> → <strong>{pendingDefault.code}</strong>
              </Text>
            )}
          </AlertDialog.Description>
          <Flex gap="3" mt="4" justify="end">
            <AlertDialog.Cancel>
              <Button variant="soft" color="gray">
                {labels.cancelButton}
              </Button>
            </AlertDialog.Cancel>
            <AlertDialog.Action>
              <Button
                onClick={() => {
                  if (pendingDefault) setDefaultLanguage.mutate(pendingDefault.id);
                  setPendingDefault(null);
                }}
              >
                {labels.setDefaultButton}
              </Button>
            </AlertDialog.Action>
          </Flex>
        </AlertDialog.Content>
      </AlertDialog.Root>
    </Box>
  );
}

function LanguageCreateDialog({
  labels,
  onClose,
  onSave,
}: {
  labels: LanguagesAdminLabels;
  onClose: () => void;
  onSave: (payload: { code: string; name: string; is_active: boolean; is_default: boolean }) => void;
}) {
  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [isActive, setIsActive] = useState(true);

  return (
    <Dialog.Root open onOpenChange={(o) => !o && onClose()}>
      <Dialog.Content style={{ maxWidth: 480 }}>
        <Dialog.Title>{labels.createModalTitle}</Dialog.Title>
        <Flex direction="column" gap="3" mt="4">
          <label>
            <Text as="div" size="2" mb="1" weight="bold">
              {labels.fields.code}
            </Text>
            <TextField.Root
              value={code}
              placeholder="pt_BR"
              onChange={(e) => setCode(e.target.value)}
            />
          </label>
          <label>
            <Text as="div" size="2" mb="1" weight="bold">
              {labels.fields.name}
            </Text>
            <TextField.Root value={name} onChange={(e) => setName(e.target.value)} />
          </label>
          <Flex align="center" gap="2">
            <Switch checked={isActive} onCheckedChange={setIsActive} />
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
            disabled={!code || !name}
            onClick={() =>
              onSave({
                code,
                name,
                is_active: isActive,
                is_default: false,
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

function LanguageEditDialog({
  language,
  labels,
  onClose,
  onSave,
}: {
  language: Language;
  labels: LanguagesAdminLabels;
  onClose: () => void;
  onSave: (payload: { name: string; is_active: boolean }) => void;
}) {
  const [name, setName] = useState(language.name ?? '');
  const [isActive, setIsActive] = useState(language.is_active ?? true);

  return (
    <Dialog.Root open onOpenChange={(o) => !o && onClose()}>
      <Dialog.Content style={{ maxWidth: 480 }}>
        <Dialog.Title>{labels.editModalTitle}</Dialog.Title>
        <Flex direction="column" gap="3" mt="4">
          <label>
            <Text as="div" size="2" mb="1" weight="bold">
              {labels.fields.name}
            </Text>
            <TextField.Root value={name} onChange={(e) => setName(e.target.value)} />
          </label>
          <Flex align="center" gap="2">
            <Switch checked={isActive} onCheckedChange={setIsActive} />
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
                name,
                is_active: isActive,
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
