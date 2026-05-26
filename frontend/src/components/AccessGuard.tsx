import { createContext, useContext, type ReactNode } from 'react';

/**
 * Context that supplies the list of abilities granted to the current
 * user. Hosts wrap their app (or a subtree) in `<AccessGuardProvider
 * abilities={...} />`; nested `<AccessGuard ability="...">` components
 * then read it without prop drilling.
 *
 * The package does NOT fetch this list itself — `GET /me/abilities`
 * is not part of the public REST surface. Hosts already know what the
 * current user can do (auth response, JWT claims, session, etc.) and
 * pass it in.
 */
const AccessGuardContext = createContext<string[] | null>(null);

export interface AccessGuardProviderProps {
  abilities: string[];
  children: ReactNode;
}

export function AccessGuardProvider({ abilities, children }: AccessGuardProviderProps) {
  return (
    <AccessGuardContext.Provider value={abilities}>{children}</AccessGuardContext.Provider>
  );
}

/**
 * Hook form. Returns `true` when the user has the named ability,
 * `false` otherwise. Throws when called outside any
 * `<AccessGuardProvider>` AND `abilities` is not passed inline —
 * letting the host opt into per-call abilities for ad-hoc checks.
 */
export function useHasAbility(ability: string, abilities?: string[]): boolean {
  const ctx = useContext(AccessGuardContext);
  const source = abilities ?? ctx;
  if (source === null || source === undefined) {
    throw new Error(
      '[admin-react] useHasAbility() called outside <AccessGuardProvider>. ' +
        'Either wrap your app in <AccessGuardProvider abilities={...}> or ' +
        'pass `abilities` directly to the hook.',
    );
  }

  return source.includes(ability);
}

export interface AccessGuardProps {
  ability: string;
  abilities?: string[];
  fallback?: ReactNode;
  children: ReactNode;
}

/**
 * Renders `children` when the current user has `ability`, otherwise
 * renders `fallback` (defaults to `null` — i.e. nothing). Use this
 * for conditional rendering of buttons, menu items, route components,
 * etc.
 *
 * Example:
 *   <AccessGuard ability="events.delete" fallback={<DisabledHint />}>
 *     <DeleteButton />
 *   </AccessGuard>
 */
export function AccessGuard({
  ability,
  abilities,
  fallback = null,
  children,
}: AccessGuardProps) {
  const granted = useHasAbility(ability, abilities);

  return <>{granted ? children : fallback}</>;
}
