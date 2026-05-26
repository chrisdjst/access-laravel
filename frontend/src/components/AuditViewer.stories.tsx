import type { Meta, StoryObj } from '@storybook/react';
import { http, HttpResponse } from 'msw';

import { AuditViewer } from './AuditViewer.js';

const API = 'http://app.test/api/admin';

const meta: Meta<typeof AuditViewer> = {
  title: 'Components/AuditViewer',
  component: AuditViewer,
  parameters: { layout: 'fullscreen' },
};

export default meta;
type Story = StoryObj<typeof AuditViewer>;

const makeEntry = (overrides: Record<string, unknown> = {}) => ({
  id: crypto.randomUUID(),
  event_name: 'module.created',
  actor_id: '55555555-5555-5555-5555-555555555555',
  tenant_id: null,
  payload: {
    module_id: '11111111-1111-1111-1111-111111111111',
    slug: 'events',
    name: 'Events',
  },
  occurred_at: '2026-05-25T12:00:00+00:00',
  entry_hash: 'abc123def456abc123def456abc123def456abc123def456abc123def456abcd',
  previous_hash: null,
  ...overrides,
});

const EVENTS = [
  'module.created',
  'module.updated',
  'role.created',
  'role.modules_synced',
  'language.default_changed',
  'role.deleted',
  'module.deleted',
  'language.created',
];

export const SmallDataset: Story = {
  parameters: {
    msw: {
      handlers: [
        http.get(`${API}/audit`, () =>
          HttpResponse.json({
            data: [
              makeEntry({
                event_name: 'module.created',
                payload: { slug: 'events', name: 'Events' },
              }),
              makeEntry({
                event_name: 'role.created',
                payload: { name: 'editor', display_name: 'Editor' },
              }),
              makeEntry({
                event_name: 'role.modules_synced',
                payload: {
                  role_id: '22222222-2222-2222-2222-222222222222',
                  user_email: '[REDACTED]',
                  changes: 4,
                },
              }),
              makeEntry({
                event_name: 'language.default_changed',
                tenant_id: '66666666-6666-6666-6666-666666666666',
                payload: { from: 'en', to: 'pt_BR' },
              }),
              makeEntry({
                event_name: 'module.deleted',
                entry_hash: null,
                payload: { module_id: '11111111-1111-1111-1111-111111111111' },
              }),
              makeEntry({
                event_name: 'role.deleted',
                payload: { role_id: '22222222-2222-2222-2222-222222222222' },
              }),
              makeEntry({
                event_name: 'language.created',
                payload: { code: 'es', name: 'Español' },
              }),
              makeEntry({
                event_name: 'module.updated',
                payload: {
                  module_id: '11111111-1111-1111-1111-111111111111',
                  changes: { name: 'Events (new)' },
                  ip_address: '[REDACTED]',
                },
              }),
              makeEntry({
                event_name: 'role.created',
                payload: { name: 'reviewer' },
              }),
              makeEntry({
                event_name: 'module.created',
                payload: { slug: 'reports', name: 'Reports' },
              }),
            ],
            meta: { total: 10, limit: 25, offset: 0 },
          }),
        ),
      ],
    },
  },
};

export const LargeDatasetWithPagination: Story = {
  parameters: {
    msw: {
      handlers: [
        http.get(`${API}/audit`, ({ request }) => {
          const url = new URL(request.url);
          const offset = parseInt(url.searchParams.get('offset') ?? '0', 10);
          const limit = parseInt(url.searchParams.get('limit') ?? '25', 10);
          const eventFilter = url.searchParams.get('event');

          let all = Array.from({ length: 500 }, (_, i) =>
            makeEntry({
              id: `entry-${i}`,
              event_name: EVENTS[i % EVENTS.length] ?? 'module.created',
              payload: { iteration: i, label: `Event ${i + 1}` },
              occurred_at: new Date(Date.now() - i * 60_000).toISOString(),
            }),
          );
          if (eventFilter) {
            all = all.filter((e) => e.event_name === eventFilter);
          }

          return HttpResponse.json({
            data: all.slice(offset, offset + limit),
            meta: { total: all.length, limit, offset },
          });
        }),
      ],
    },
  },
};
