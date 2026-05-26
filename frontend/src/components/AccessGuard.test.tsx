import { render, renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { AccessGuard, AccessGuardProvider, useHasAbility } from './AccessGuard.js';

describe('AccessGuard', () => {
  it('renders children when ability is granted via provider', () => {
    const { getByText, queryByText } = render(
      <AccessGuardProvider abilities={['events.delete', 'events.create']}>
        <AccessGuard ability="events.delete" fallback={<span>denied</span>}>
          <span>granted</span>
        </AccessGuard>
      </AccessGuardProvider>,
    );

    expect(getByText('granted')).toBeTruthy();
    expect(queryByText('denied')).toBeNull();
  });

  it('renders fallback when ability is missing', () => {
    const { getByText, queryByText } = render(
      <AccessGuardProvider abilities={['events.read']}>
        <AccessGuard ability="events.delete" fallback={<span>denied</span>}>
          <span>granted</span>
        </AccessGuard>
      </AccessGuardProvider>,
    );

    expect(getByText('denied')).toBeTruthy();
    expect(queryByText('granted')).toBeNull();
  });

  it('renders nothing (null fallback) when no fallback is provided', () => {
    const { container } = render(
      <AccessGuardProvider abilities={['events.read']}>
        <AccessGuard ability="events.delete">
          <span>granted</span>
        </AccessGuard>
      </AccessGuardProvider>,
    );

    expect(container.textContent).toBe('');
  });

  it('accepts an inline abilities prop without a provider', () => {
    const { getByText } = render(
      <AccessGuard ability="x" abilities={['x']}>
        <span>granted</span>
      </AccessGuard>,
    );

    expect(getByText('granted')).toBeTruthy();
  });
});

describe('useHasAbility', () => {
  it('returns true when the ability is in the provider list', () => {
    const wrapper = ({ children }: { children: React.ReactNode }) => (
      <AccessGuardProvider abilities={['a', 'b']}>{children}</AccessGuardProvider>
    );
    const { result } = renderHook(() => useHasAbility('a'), { wrapper });

    expect(result.current).toBe(true);
  });

  it('returns false when the ability is missing', () => {
    const wrapper = ({ children }: { children: React.ReactNode }) => (
      <AccessGuardProvider abilities={['a']}>{children}</AccessGuardProvider>
    );
    const { result } = renderHook(() => useHasAbility('x'), { wrapper });

    expect(result.current).toBe(false);
  });

  it('throws when called without provider AND without inline abilities', () => {
    expect(() => renderHook(() => useHasAbility('x'))).toThrow(/AccessGuardProvider/);
  });
});
