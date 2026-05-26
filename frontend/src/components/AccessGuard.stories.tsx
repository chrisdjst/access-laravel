import { Box, Button, Card, Flex, Heading, Text } from '@radix-ui/themes';
import type { Meta, StoryObj } from '@storybook/react';

import { AccessGuard, AccessGuardProvider } from './AccessGuard.js';

const meta: Meta<typeof AccessGuard> = {
  title: 'Components/AccessGuard',
  component: AccessGuard,
  parameters: { layout: 'centered' },
};

export default meta;
type Story = StoryObj<typeof AccessGuard>;

function DemoToolbar() {
  return (
    <Card>
      <Box p="4" style={{ width: 360 }}>
        <Heading size="3" mb="3">
          Events toolbar
        </Heading>
        <Flex gap="2" wrap="wrap">
          <AccessGuard ability="events.create">
            <Button>Create</Button>
          </AccessGuard>
          <AccessGuard
            ability="events.delete"
            fallback={
              <Button color="red" disabled>
                Delete (locked)
              </Button>
            }
          >
            <Button color="red">Delete</Button>
          </AccessGuard>
          <AccessGuard ability="events.archive" fallback={null}>
            <Button variant="soft">Archive</Button>
          </AccessGuard>
        </Flex>
        <Text size="1" color="gray" mt="3" as="p">
          Only buttons whose abilities are in the provider show up. The Delete button has a
          disabled fallback; the Archive button silently disappears when missing.
        </Text>
      </Box>
    </Card>
  );
}

export const Granted: Story = {
  render: () => (
    <AccessGuardProvider abilities={['events.create', 'events.delete', 'events.archive']}>
      <DemoToolbar />
    </AccessGuardProvider>
  ),
};

export const PartialGrant: Story = {
  render: () => (
    <AccessGuardProvider abilities={['events.create']}>
      <DemoToolbar />
    </AccessGuardProvider>
  ),
};

export const Denied: Story = {
  render: () => (
    <AccessGuardProvider abilities={[]}>
      <DemoToolbar />
    </AccessGuardProvider>
  ),
};

export const InlineAbilities: Story = {
  render: () => (
    <Card>
      <Box p="4" style={{ width: 360 }}>
        <Heading size="3" mb="3">
          Without a provider
        </Heading>
        <AccessGuard
          ability="audit.read"
          abilities={['audit.read']}
          fallback={<Text>Sem acesso</Text>}
        >
          <Button>Open audit log</Button>
        </AccessGuard>
        <Text size="1" color="gray" mt="3" as="p">
          Useful for ad-hoc per-call checks (e.g. inside a route loader) without wiring a
          provider.
        </Text>
      </Box>
    </Card>
  ),
};
