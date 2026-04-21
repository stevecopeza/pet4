/**
 * usePortalUser
 *
 * Reads the current WP user context injected by PortalShortcode into
 * window.petSettings. Returns capability flags used for route-gating
 * and conditional UI rendering.
 */

export interface PortalUser {
  id: number;
  displayName: string;
  initials: string;
  isSales: boolean;
  isHr: boolean;
  isManager: boolean;
  isAdmin: boolean;
  /** True if the user holds the pet_staff capability (regular staff, no elevated role) */
  isStaff: boolean;
  /** True if the user holds any portal capability */
  hasPortalAccess: boolean;
}

export function usePortalUser(): PortalUser {
  // @ts-ignore
  const settings = (window.petSettings ?? {}) as Record<string, any>;
  const caps: string[] = settings.currentUserCaps ?? [];
  const id: number = settings.currentUserId ?? 0;
  const displayName: string = settings.currentUserDisplayName ?? 'User';

  const isSales   = caps.includes('pet_sales');
  const isHr      = caps.includes('pet_hr');
  const isManager = caps.includes('pet_manager');
  const isAdmin   = caps.includes('manage_options');
  const isStaff   = caps.includes('pet_staff');

  const words = displayName.trim().split(/\s+/);
  const initials =
    words.length >= 2
      ? (words[0][0] + words[words.length - 1][0]).toUpperCase()
      : displayName.slice(0, 2).toUpperCase();

  return {
    id,
    displayName,
    initials,
    isSales,
    isHr,
    isManager,
    isAdmin,
    isStaff,
    hasPortalAccess: isSales || isHr || isManager || isAdmin || isStaff,
  };
}
