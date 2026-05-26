import {
  Badge,
  Box,
  Button,
  Card,
  Code,
  Flex,
  Heading,
  IconButton,
  Select,
  Table,
  Text,
  TextField,
  Tooltip,
} from '@radix-ui/themes';
import { useMemo, useState } from 'react';

import type { components } from '../api/rbac.js';
import { useAdminAudit, type AuditIndexQuery } from '../hooks/useRbac.js';

type AuditEntry = components['schemas']['AuditEntry'] & { id: string; event_name: string };

export interface AuditViewerLabels {
  title: string;
  filtersLabel: string;
  applyFiltersButton: string;
  clearFiltersButton: string;
  prevPageButton: string;
  nextPageButton: string;
  expandButton: string;
  collapseButton: string;
  loading: string;
  empty: string;
  chainOkBadge: string;
  chainBrokenBadge: string;
  redactedTag: string;
  paginationLabel: (offset: number, limit: number, total: number) => string;
  filters: {
    event: string;
    actorId: string;
    tenantId: string;
    since: string;
    until: string;
  };
  columns: {
    occurredAt: string;
    event: string;
    actor: string;
    tenant: string;
    payload: string;
    chain: string;
  };
}

const DEFAULT_LABELS: AuditViewerLabels = {
  title: 'Audit log',
  filtersLabel: 'Filters',
  applyFiltersButton: 'Apply',
  clearFiltersButton: 'Clear',
  prevPageButton: 'Previous',
  nextPageButton: 'Next',
  expandButton: 'Expand',
  collapseButton: 'Collapse',
  loading: 'Loading audit log…',
  empty: 'No audit entries match the current filters.',
  chainOkBadge: 'chain ok',
  chainBrokenBadge: 'chain broken',
  redactedTag: 'REDACTED',
  paginationLabel: (offset, limit, total) =>
    total === 0
      ? '0 of 0'
      : `${offset + 1}-${Math.min(offset + limit, total)} of ${total}`,
  filters: {
    event: 'Event',
    actorId: 'Actor ID',
    tenantId: 'Tenant ID',
    since: 'Since',
    until: 'Until',
  },
  columns: {
    occurredAt: 'Occurred at',
    event: 'Event',
    actor: 'Actor',
    tenant: 'Tenant',
    payload: 'Payload',
    chain: 'Chain',
  },
};

const COMMON_EVENTS = [
  '',
  'module.created',
  'module.updated',
  'module.deleted',
  'role.created',
  'role.updated',
  'role.deleted',
  'role.modules_synced',
  'language.created',
  'language.updated',
  'language.deleted',
  'language.default_changed',
];

export interface AuditViewerProps {
  limit?: number;
  labels?: Partial<AuditViewerLabels>;
}

function mergeLabels(partial?: Partial<AuditViewerLabels>): AuditViewerLabels {
  return {
    ...DEFAULT_LABELS,
    ...partial,
    filters: { ...DEFAULT_LABELS.filters, ...(partial?.filters ?? {}) },
    columns: { ...DEFAULT_LABELS.columns, ...(partial?.columns ?? {}) },
  };
}

function isoToInputDateTime(iso: string): string {
  // datetime-local input wants YYYY-MM-DDTHH:mm — no timezone, no seconds.
  return iso.slice(0, 16);
}

export function AuditViewer({ limit = 25, labels: labelsProp }: AuditViewerProps = {}) {
  const labels = mergeLabels(labelsProp);
  const [offset, setOffset] = useState(0);
  type Filters = NonNullable<AuditIndexQuery>;
  const [filters, setFilters] = useState<Filters>({});
  const [draftFilters, setDraftFilters] = useState<Filters>({});
  const [expandedId, setExpandedId] = useState<string | null>(null);

  const query = useAdminAudit({ ...filters, limit, offset });
  const envelope = query.data as unknown as
    | { data?: AuditEntry[]; meta?: { total?: number } }
    | undefined;
  const rows = useMemo<AuditEntry[]>(() => (envelope?.data ?? []) as AuditEntry[], [envelope]);
  const total = envelope?.meta?.total ?? rows.length;

  const applyFilters = () => {
    setFilters(draftFilters);
    setOffset(0);
  };
  const clearFilters = () => {
    setDraftFilters({});
    setFilters({});
    setOffset(0);
  };

  return (
    <Box p="4">
      <Heading size="6" mb="3">
        {labels.title}
      </Heading>

      <Card mb="3">
        <Box p="3">
          <Text as="div" size="2" weight="bold" mb="2">
            {labels.filtersLabel}
          </Text>
          <Flex gap="2" wrap="wrap" align="end">
            <Box>
              <Text as="div" size="1" color="gray">
                {labels.filters.event}
              </Text>
              <Select.Root
                value={(draftFilters.event ?? '') as string}
                onValueChange={(v) =>
                  setDraftFilters({ ...draftFilters, event: v || undefined })
                }
              >
                <Select.Trigger style={{ minWidth: 200 }} placeholder="—" />
                <Select.Content>
                  {COMMON_EVENTS.map((ev) => (
                    <Select.Item key={ev || 'any'} value={ev || 'any'}>
                      {ev || '— any —'}
                    </Select.Item>
                  ))}
                </Select.Content>
              </Select.Root>
            </Box>
            <Box>
              <Text as="div" size="1" color="gray">
                {labels.filters.actorId}
              </Text>
              <TextField.Root
                style={{ minWidth: 220 }}
                placeholder="uuid"
                value={draftFilters.actor_id ?? ''}
                onChange={(e) =>
                  setDraftFilters({ ...draftFilters, actor_id: e.target.value || undefined })
                }
              />
            </Box>
            <Box>
              <Text as="div" size="1" color="gray">
                {labels.filters.tenantId}
              </Text>
              <TextField.Root
                style={{ minWidth: 220 }}
                placeholder="uuid"
                value={draftFilters.tenant_id ?? ''}
                onChange={(e) =>
                  setDraftFilters({ ...draftFilters, tenant_id: e.target.value || undefined })
                }
              />
            </Box>
            <Box>
              <Text as="div" size="1" color="gray">
                {labels.filters.since}
              </Text>
              <input
                type="datetime-local"
                value={draftFilters.since ? isoToInputDateTime(draftFilters.since) : ''}
                onChange={(e) =>
                  setDraftFilters({
                    ...draftFilters,
                    since: e.target.value ? `${e.target.value}:00Z` : undefined,
                  })
                }
              />
            </Box>
            <Box>
              <Text as="div" size="1" color="gray">
                {labels.filters.until}
              </Text>
              <input
                type="datetime-local"
                value={draftFilters.until ? isoToInputDateTime(draftFilters.until) : ''}
                onChange={(e) =>
                  setDraftFilters({
                    ...draftFilters,
                    until: e.target.value ? `${e.target.value}:00Z` : undefined,
                  })
                }
              />
            </Box>
            <Flex gap="2">
              <Button onClick={applyFilters}>{labels.applyFiltersButton}</Button>
              <Button variant="soft" color="gray" onClick={clearFilters}>
                {labels.clearFiltersButton}
              </Button>
            </Flex>
          </Flex>
        </Box>
      </Card>

      {query.isLoading ? (
        <Box p="4">
          <Text>{labels.loading}</Text>
        </Box>
      ) : rows.length === 0 ? (
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
                <Table.ColumnHeaderCell width="190px">
                  {labels.columns.occurredAt}
                </Table.ColumnHeaderCell>
                <Table.ColumnHeaderCell>{labels.columns.event}</Table.ColumnHeaderCell>
                <Table.ColumnHeaderCell>{labels.columns.actor}</Table.ColumnHeaderCell>
                <Table.ColumnHeaderCell>{labels.columns.tenant}</Table.ColumnHeaderCell>
                <Table.ColumnHeaderCell>{labels.columns.chain}</Table.ColumnHeaderCell>
                <Table.ColumnHeaderCell width="120px">
                  {labels.columns.payload}
                </Table.ColumnHeaderCell>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {rows.map((entry) => {
                const expanded = expandedId === entry.id;
                const chainOk =
                  entry.entry_hash !== null && entry.entry_hash !== undefined;

                return (
                  <>
                    <Table.Row key={entry.id}>
                      <Table.Cell>
                        <Text size="1" style={{ fontFamily: 'monospace' }}>
                          {entry.occurred_at ?? '—'}
                        </Text>
                      </Table.Cell>
                      <Table.Cell>
                        <Code>{entry.event_name}</Code>
                      </Table.Cell>
                      <Table.Cell>
                        <Text size="1" color="gray" style={{ fontFamily: 'monospace' }}>
                          {entry.actor_id ?? '—'}
                        </Text>
                      </Table.Cell>
                      <Table.Cell>
                        <Text size="1" color="gray" style={{ fontFamily: 'monospace' }}>
                          {entry.tenant_id ?? '—'}
                        </Text>
                      </Table.Cell>
                      <Table.Cell>
                        {chainOk ? (
                          <Tooltip
                            content={`sha256 prefix ${(entry.entry_hash ?? '').slice(0, 12)}…`}
                          >
                            <Badge color="green">{labels.chainOkBadge}</Badge>
                          </Tooltip>
                        ) : (
                          <Text size="1" color="gray">
                            —
                          </Text>
                        )}
                      </Table.Cell>
                      <Table.Cell>
                        <IconButton
                          size="1"
                          variant="ghost"
                          onClick={() => setExpandedId(expanded ? null : entry.id)}
                          aria-label={expanded ? labels.collapseButton : labels.expandButton}
                        >
                          {expanded ? '▾' : '▸'}
                        </IconButton>
                      </Table.Cell>
                    </Table.Row>
                    {expanded && (
                      <Table.Row key={`${entry.id}-payload`}>
                        <Table.Cell colSpan={6}>
                          <PayloadView payload={entry.payload} redactedTag={labels.redactedTag} />
                        </Table.Cell>
                      </Table.Row>
                    )}
                  </>
                );
              })}
            </Table.Body>
          </Table.Root>
        </Card>
      )}

      <Flex justify="between" align="center" mt="3">
        <Text size="2" color="gray">
          {labels.paginationLabel(offset, limit, total)}
        </Text>
        <Flex gap="2">
          <Button
            variant="soft"
            disabled={offset === 0}
            onClick={() => setOffset(Math.max(0, offset - limit))}
          >
            {labels.prevPageButton}
          </Button>
          <Button
            variant="soft"
            disabled={offset + limit >= total}
            onClick={() => setOffset(offset + limit)}
          >
            {labels.nextPageButton}
          </Button>
        </Flex>
      </Flex>
    </Box>
  );
}

function PayloadView({ payload, redactedTag }: { payload: unknown; redactedTag: string }) {
  const formatted = useMemo(() => JSON.stringify(payload, null, 2), [payload]);

  // Highlight `[REDACTED]` markers (audit_pii_redaction replaces sensitive
  // strings with this literal) so they're visually distinct in the dump.
  const segments = useMemo(() => {
    const out: Array<{ text: string; redacted: boolean }> = [];
    const needle = '"[REDACTED]"';
    let idx = 0;
    let cursor = 0;
    while ((idx = formatted.indexOf(needle, cursor)) !== -1) {
      if (idx > cursor) out.push({ text: formatted.slice(cursor, idx), redacted: false });
      out.push({ text: needle, redacted: true });
      cursor = idx + needle.length;
    }
    if (cursor < formatted.length) {
      out.push({ text: formatted.slice(cursor), redacted: false });
    }

    return out;
  }, [formatted]);

  return (
    <Box
      p="3"
      style={{
        background: 'var(--gray-3)',
        borderRadius: 6,
        fontFamily: 'monospace',
        fontSize: 12,
        whiteSpace: 'pre',
        overflowX: 'auto',
      }}
    >
      {segments.map((seg, i) =>
        seg.redacted ? (
          <span
            key={i}
            title={redactedTag}
            style={{
              background: 'var(--amber-4)',
              color: 'var(--amber-11)',
              padding: '0 2px',
              borderRadius: 3,
            }}
          >
            {seg.text}
          </span>
        ) : (
          <span key={i}>{seg.text}</span>
        ),
      )}
    </Box>
  );
}
