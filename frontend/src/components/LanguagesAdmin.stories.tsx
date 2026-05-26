import type { Meta, StoryObj } from '@storybook/react';
import { http, HttpResponse } from 'msw';

import { LanguagesAdmin } from './LanguagesAdmin.js';

const API = 'http://app.test/api/admin';

const meta: Meta<typeof LanguagesAdmin> = {
  title: 'Components/LanguagesAdmin',
  component: LanguagesAdmin,
  parameters: { layout: 'fullscreen' },
};

export default meta;
type Story = StoryObj<typeof LanguagesAdmin>;

const makeLanguage = (overrides: Record<string, unknown> = {}) => ({
  id: crypto.randomUUID(),
  code: 'en',
  name: 'English',
  is_default: false,
  is_active: true,
  ...overrides,
});

export const FiveSeeded: Story = {
  parameters: {
    msw: {
      handlers: [
        http.get(`${API}/languages`, () =>
          HttpResponse.json({
            data: [
              makeLanguage({ id: 'en', code: 'en', name: 'English', is_default: true }),
              makeLanguage({ id: 'pt-br', code: 'pt_BR', name: 'Português (Brasil)' }),
              makeLanguage({ id: 'es', code: 'es', name: 'Español' }),
              makeLanguage({ id: 'fr', code: 'fr', name: 'Français' }),
              makeLanguage({
                id: 'de',
                code: 'de',
                name: 'Deutsch',
                is_active: false,
              }),
            ],
          }),
        ),
      ],
    },
  },
};

export const SingleDefault: Story = {
  parameters: {
    msw: {
      handlers: [
        http.get(`${API}/languages`, () =>
          HttpResponse.json({
            data: [makeLanguage({ id: 'en', code: 'en', name: 'English', is_default: true })],
          }),
        ),
      ],
    },
  },
};

export const Empty: Story = {
  parameters: {
    msw: {
      handlers: [
        http.get(`${API}/languages`, () => HttpResponse.json({ data: [] })),
      ],
    },
  },
};
