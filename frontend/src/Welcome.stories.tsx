import { Card, Flex, Heading, Link, Text } from '@radix-ui/themes';
import type { Meta, StoryObj } from '@storybook/react';

const Welcome = () => (
  <Flex direction="column" gap="4" maxWidth="640px">
    <Heading size="6">@modularize-rbac/admin-react</Heading>
    <Text size="3" color="gray">
      React hooks + admin components for{' '}
      <Link href="https://github.com/chrisdjst/access-laravel" target="_blank" rel="noreferrer">
        modularize-rbac/laravel
      </Link>
      . Pick a component from the sidebar to see it in action.
    </Text>

    <Card>
      <Flex direction="column" gap="2">
        <Heading size="3">What's in this Storybook</Heading>
        <Text as="div" size="2">
          <ul>
            <li>
              <code>RolesPage</code> — paginated role list + create/clone/restore + permission
              matrix.
            </li>
            <li>
              <code>ModulesTreeEditor</code> — drag-and-drop tree with bulk delete.
            </li>
            <li>
              <code>LanguagesAdmin</code> — language CRUD + default toggle.
            </li>
            <li>
              <code>AuditViewer</code> — paginated audit timeline with filters + JSON inspector.
            </li>
            <li>
              <code>AccessGuard</code> — wrapper that hides children based on the host's ability
              list.
            </li>
          </ul>
        </Text>
      </Flex>
    </Card>

    <Card>
      <Flex direction="column" gap="2">
        <Heading size="3">Wired with</Heading>
        <Text as="div" size="2">
          <ul>
            <li>Radix Themes (light, indigo accent, medium radius).</li>
            <li>
              <code>QueryClientProvider</code> per story (no cross-story cache pollution).
            </li>
            <li>
              <code>RbacProvider</code> built on top of <code>@modularize-rbac/sdk-ts</code>.
            </li>
            <li>
              msw handlers — happy-path data wired in <code>.storybook/msw-handlers.ts</code>.
              Stories override per-test via{' '}
              <code>parameters.msw.handlers</code>.
            </li>
          </ul>
        </Text>
      </Flex>
    </Card>
  </Flex>
);

const meta: Meta<typeof Welcome> = {
  title: 'Welcome',
  component: Welcome,
  parameters: { layout: 'centered' },
};

export default meta;

export const Default: StoryObj<typeof Welcome> = {};
