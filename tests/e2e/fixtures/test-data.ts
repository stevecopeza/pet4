/**
 * Shared test data constants.
 * All E2E-created entities use the 'E2E Test' prefix for easy identification and cleanup.
 */

export function testLabel(entity: string): string {
  return `E2E Test ${entity} - ${Date.now()}`;
}

/** All PET admin page slugs (from AdminPageRegistry) */
export const ADMIN_PAGE_SLUGS = [
  'pet-dashboard',
  'pet-dashboards',
  'pet-crm',
  'pet-quotes-sales',
  'pet-finance',
  'pet-delivery',
  'pet-time',
  'pet-support',
  'pet-conversations',
  'pet-approvals',
  'pet-knowledge',
  'pet-people',
  'pet-roles',
  'pet-activity',
  'pet-settings',
  'pet-pulseway',
  'pet-shortcodes',
  'pet-demo-tools',
] as const;

/** Mapping of page slug to expected heading text */
export const PAGE_TITLES: Record<string, string> = {
  'pet-dashboard': 'Overview',
  'pet-dashboards': 'Dashboards',
  'pet-crm': 'Customers',
  'pet-quotes-sales': 'Quotes & Sales',
  'pet-finance': 'Finance',
  'pet-delivery': 'Delivery',
  'pet-time': 'Time',
  'pet-support': 'Support',
  'pet-conversations': 'Conversations',
  'pet-approvals': 'Approvals',
  'pet-knowledge': 'Knowledge',
  'pet-people': 'Staff',
  'pet-roles': 'Roles & Capabilities',
  'pet-activity': 'Activity',
  'pet-settings': 'Settings',
  'pet-pulseway': 'Pulseway RMM',
  'pet-shortcodes': 'Shortcodes',
  'pet-demo-tools': 'Demo Tools',
};
