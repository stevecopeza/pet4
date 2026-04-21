import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Quote, QuoteBlock, QuoteSection, Employee, Team, Customer } from '../types';
import { computeQuoteTotals, isQuoteLevelAdjustmentSection, sortSections, generateLocalId, getBaseBlockValue } from '../utils/quoteTotals';
import MarkdownTextarea from './MarkdownTextarea';
import BlockRow, { BlockRowCallbacks } from './BlockRow';
import ServiceBlockEditor from './ServiceBlockEditor';
import ProjectBlockEditor, { computeProjectSummary } from './ProjectBlockEditor';
import ConfirmationDialog from './foundation/ConfirmationDialog';
import useToast from './foundation/useToast';
import LoadingState from './foundation/states/LoadingState';
import ErrorState from './foundation/states/ErrorState';
import EmptyState from './foundation/states/EmptyState';

const flattenTeams = (nodes: Team[]): Team[] => {
  let flat: Team[] = [];
  nodes.forEach((node) => {
    flat.push(node);
    if (Array.isArray(node.children) && node.children.length > 0) {
      flat = flat.concat(flattenTeams(node.children));
    }
  });
  return flat;
};
import MalleableFieldsRenderer from './MalleableFieldsRenderer';
import AddCostAdjustmentForm from './AddCostAdjustmentForm';
import KebabMenu, { KebabMenuItem } from './KebabMenu';
import useConversation from '../hooks/useConversation';
import useConversationStatus from '../hooks/useConversationStatus';
import { computeQuoteHealth } from '../healthCompute';

/** Returns true if a project block's draft differs from its server payload. */
const isProjectBlockDirty = (draft: any, payload: any): boolean => {
  if (!draft) return false;
  const p = payload || {};
  if ((draft.description ?? '') !== (p.description ?? '')) return true;
  const dPhases = Array.isArray(draft.phases) ? draft.phases : [];
  const pPhases = Array.isArray(p.phases) ? p.phases : [];
  if (dPhases.length !== pPhases.length) return true;
  for (let i = 0; i < dPhases.length; i++) {
    const dp = dPhases[i];
    const pp = pPhases[i];
    if ((dp.name ?? '') !== (pp.name ?? '')) return true;
    const dUnits = Array.isArray(dp.units) ? dp.units : [];
    const pUnits = Array.isArray(pp.units) ? pp.units : [];
    if (dUnits.length !== pUnits.length) return true;
    for (let j = 0; j < dUnits.length; j++) {
      const du = dUnits[j];
      const pu = pUnits[j];
      if ((du.description ?? '') !== (pu.description ?? '')) return true;
      if (Number(du.quantity ?? 0) !== Number(pu.quantity ?? 0)) return true;
      if ((du.unit ?? 'hours') !== (pu.unit ?? 'hours')) return true;
      if (Number(du.unitPrice ?? 0) !== Number(pu.unitPrice ?? 0)) return true;
      if ((du.roleId ?? null) !== (pu.roleId ?? null)) return true;
      if ((du.ownerType ?? '') !== (pu.ownerType ?? '')) return true;
      if ((du.ownerId ?? null) !== (pu.ownerId ?? null)) return true;
      if ((du.teamId ?? null) !== (pu.teamId ?? null)) return true;
      if ((du.catalogItemId ?? null) !== (pu.catalogItemId ?? null)) return true;
      if (Boolean(du.price_override) !== Boolean(pu.price_override)) return true;
    }
  }
  return false;
};


interface QuoteDetailsProps {
  quoteId: number;
  onBack: () => void;
}

const QuoteDetails: React.FC<QuoteDetailsProps> = ({ quoteId, onBack }) => {
  const toast = useToast();
  const [quote, setQuote] = useState<Quote | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);
  const [catalogItems, setCatalogItems] = useState<{ id: number; name: string; unit_price: number; unit_cost: number; type: string }[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [roles, setRoles] = useState<{ id: number; name: string }[]>([]);
  const [rateCards, setRateCards] = useState<{ id: number; role_id: number; service_type_id: number; sell_rate: number; status: string }[]>([]);
  const [ownerOptionsCache, setOwnerOptionsCache] = useState<Record<number, {
    recommended_teams: { id: number; name: string; is_primary?: boolean }[];
    recommended_employees: { id: number; name: string }[];
    other_teams: { id: number; name: string }[];
    other_employees: { id: number; name: string }[];
  }>>({});
  
  const [showTypeSelection, setShowTypeSelection] = useState(false);
  const [blockSectionIdForCreate, setBlockSectionIdForCreate] = useState<number | null>(null);
  const [showFabMenu, setShowFabMenu] = useState(false);
  const [showAdjustmentForm, setShowAdjustmentForm] = useState(false);
  const [expandedBlockId, setExpandedBlockId] = useState<number | null>(null);
  const [blockDrafts, setBlockDrafts] = useState<Record<number, any>>({});
  const [savingBlockId, setSavingBlockId] = useState<number | null>(null);
  const [serverErrors, setServerErrors] = useState<Record<number, string>>({});
  const [blockSnapshots, setBlockSnapshots] = useState<Record<number, Record<string, any>>>({});
  const [editingSectionId, setEditingSectionId] = useState<number | null>(null);
  const [sectionDraftNames, setSectionDraftNames] = useState<Record<number, string>>({});
  const [activeSubjectKeys, setActiveSubjectKeys] = useState<Set<string>>(new Set());
  const { openConversation } = useConversation();
  const quoteIdArr = useMemo(() => [String(quoteId)], [quoteId]);
  const { statuses: quoteConvStatuses } = useConversationStatus('quote', quoteIdArr);
  const [editingHeaderField, setEditingHeaderField] = useState<'title' | 'description' | null>(null);
  const [headerDraftTitle, setHeaderDraftTitle] = useState('');
  const [headerDraftDescription, setHeaderDraftDescription] = useState('');
  const [pendingConfirmation, setPendingConfirmation] = useState<{
    title: string;
    description: string;
    confirmLabel: string;
    resolve: (confirmed: boolean) => void;
  } | null>(null);
  const [showRejectForm, setShowRejectForm] = useState(false);
  const [rejectNoteInput, setRejectNoteInput] = useState('');

  const requestConfirmation = (title: string, description: string, confirmLabel: string) => new Promise<boolean>((resolve) => {
    setPendingConfirmation({ title, description, confirmLabel, resolve });
  });

  const closeConfirmation = (confirmed: boolean) => {
    setPendingConfirmation((current) => {
      if (current) {
        current.resolve(confirmed);
      }
      return null;
    });
  };

  const blocksForRendering: QuoteBlock[] = (quote?.blocks || []).slice().sort((a, b) => a.orderIndex - b.orderIndex);
  const sectionsForRendering: QuoteSection[] = sortSections(quote?.sections || [], blocksForRendering);

  const fetchSchema = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/schemas/quote?status=active`, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (response.ok) {
        const data = await response.json();
        if (Array.isArray(data) && data.length > 0) {
          setActiveSchema(data[0]);
        }
      }
    } catch (err) {
      console.error('Failed to fetch schema', err);
    }
  };
  
  const fetchQuote = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch quote details');
      }

      const data = await response.json();
      setQuote(data);
      setExpandedBlockId(null);
      setBlockDrafts({});
      setSavingBlockId(null);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An unknown error occurred');
    } finally {
      setLoading(false);
    }
  };

  const fetchCatalog = async () => {
    try {
      const response = await fetch(`${window.petSettings.apiUrl}/catalog-items`, {
        headers: { 'X-WP-Nonce': window.petSettings.nonce }
      });
      if (response.ok) {
        const data = await response.json();
        setCatalogItems(data);
      }
    } catch (err) {
      console.error('Failed to fetch catalog items', err);
    }
  };

  const fetchEmployees = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/employees`, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        console.error('Failed to fetch employees for quote view');
        return;
      }

      const data = await response.json();
      setEmployees(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error('Failed to fetch employees for quote view', err);
    }
  };

  const fetchTeams = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/teams`, {
        headers: {
          'X-WP-Nonce': nonce,
        },
      });

      if (!response.ok) {
        console.error('Failed to fetch teams for quote view');
        return;
      }

      const data = await response.json();
      const rawTeams = Array.isArray(data) ? data : [];
      setTeams(flattenTeams(rawTeams));
    } catch (err) {
      console.error('Failed to fetch teams for quote view', err);
    }
  };

  const handleSaveDraft = async () => {
    if (!quote) {
      return;
    }

    onBack();
  };

  const startEditingHeader = (field: 'title' | 'description') => {
    if (!quote || quote.state !== 'draft') return;
    if (field === 'title') {
      setHeaderDraftTitle(quote.title || '');
    } else {
      setHeaderDraftDescription(quote.description || '');
    }
    setEditingHeaderField(field);
  };

  const saveHeaderField = async () => {
    if (!quote || !editingHeaderField) return;
    const field = editingHeaderField;
    const newTitle = field === 'title' ? headerDraftTitle : quote.title;
    const newDescription = field === 'description' ? headerDraftDescription : (quote.description ?? null);

    // Skip save if unchanged
    if (field === 'title' && newTitle === quote.title) {
      setEditingHeaderField(null);
      return;
    }
    if (field === 'description' && newDescription === (quote.description ?? '')) {
      setEditingHeaderField(null);
      return;
    }

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      const response = await fetch(`${apiUrl}/quotes/${quote.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({
          customerId: quote.customerId,
          title: newTitle,
          description: newDescription,
          currency: quote.currency,
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.error || 'Failed to update quote');
      }

      const updated = await response.json();
      setQuote(updated);
      toast.success('Quote updated.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Failed to save');
    } finally {
      setEditingHeaderField(null);
    }
  };

  const fetchCustomers = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;
      const response = await fetch(`${apiUrl}/customers`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      if (response.ok) {
        const data = await response.json();
        setCustomers(data);
      }
    } catch (err) {
      console.error('Failed to fetch customers', err);
    }
  };

  const fetchRoles = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;
      const response = await fetch(`${apiUrl}/roles?status=published`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      if (response.ok) {
        const data = await response.json();
        setRoles(Array.isArray(data) ? data.map((r: any) => ({ id: r.id, name: r.name })) : []);
      }
    } catch (err) {
      console.error('Failed to fetch roles', err);
    }
  };

  const fetchRateCards = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;
      const response = await fetch(`${apiUrl}/rate-cards`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      if (response.ok) {
        const data = await response.json();
        setRateCards(Array.isArray(data) ? data.filter((rc: any) => rc.status === 'active') : []);
      }
    } catch (err) {
      console.error('Failed to fetch rate cards', err);
    }
  };

  const fetchOwnerOptions = async (roleId: number) => {
    if (ownerOptionsCache[roleId]) return;
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;
      const response = await fetch(`${apiUrl}/roles/${roleId}/owner-options`, {
        headers: { 'X-WP-Nonce': nonce },
      });
      if (response.ok) {
        const data = await response.json();
        setOwnerOptionsCache(prev => ({ ...prev, [roleId]: data }));
      }
    } catch (err) {
      console.error('Failed to fetch owner options', err);
    }
  };

  const fetchActiveSubjects = async () => {
    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;
      const response = await fetch(
        `${apiUrl}/conversations/active-subjects?context_type=quote&context_id=${quoteId}`,
        { headers: { 'X-WP-Nonce': nonce } }
      );
      if (response.ok) {
        const data: string[] = await response.json();
        setActiveSubjectKeys(new Set(data));
      }
    } catch (err) {
      console.error('Failed to fetch active subjects', err);
    }
  };

  useEffect(() => {
    fetchQuote();
    fetchSchema();
    fetchCatalog();
    fetchEmployees();
    fetchTeams();
    fetchCustomers();
    fetchRoles();
    fetchRateCards();
    fetchActiveSubjects();
  }, [quoteId]);

  // Auto-initialize drafts for project blocks (always expanded)
  useEffect(() => {
    if (!quote?.blocks) return;
    setBlockDrafts((prev) => {
      let next = prev;
      for (const block of (quote.blocks || [])) {
        if (block.type !== 'OnceOffProjectBlock') continue;
        if (next[block.id]) continue;
        const p = block.payload || {};
        if (next === prev) next = { ...prev };
        next[block.id] = {
          description: p.description ?? '',
          quantity: p.quantity ?? 1,
          sellValue: p.sellValue ?? p.totalValue ?? 0,
          owner: p.owner ?? '',
          team: p.team ?? '',
          ownerType: p.ownerType ?? '',
          ownerId: p.ownerId ?? null,
          teamId: p.teamId ?? null,
          totalValue: p.totalValue ?? p.sellValue ?? 0,
          type: block.type,
          phases: Array.isArray(p.phases) ? p.phases : [],
        };
      }
      return next;
    });
  }, [quote?.blocks]);

  const handleAddPaymentSchedule = async () => {
    if (!quote) {
      return;
    }

    if (
      Array.isArray((quote as any).paymentSchedule) &&
      (quote as any).paymentSchedule.length > 0
    ) {
      const replace = await requestConfirmation(
        'Replace payment schedule?',
        'A payment schedule already exists for this quote. Do you want to replace it with a single full-payment schedule based on the current quote total?',
        'Replace'
      );
      if (!replace) {
        return;
      }
    }

    try {
      setLoading(true);

      const blockSellTotal = (quote.blocks || []).reduce((sum, block) => {
        const payload = block.payload || {};
        const value =
          typeof payload.totalValue === 'number'
            ? payload.totalValue
            : typeof payload.sellValue === 'number'
            ? payload.sellValue
            : 0;
        return sum + value;
      }, 0);

      const schedule = [
        { title: 'Full Payment', amount: blockSellTotal, dueDate: null },
      ];

      const response = await fetch(
        `${window.petSettings.apiUrl}/quotes/${quoteId}/payment-schedule`,
        {
          method: 'POST',
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ milestones: schedule }),
        }
      );

      const payload = await response.json().catch(() => null);

      if (!response.ok) {
        const message =
          payload && typeof payload.error === 'string'
            ? payload.error
            : 'Failed to set payment schedule';
        throw new Error(message);
      }

      if (payload && typeof payload === 'object') {
        setQuote(payload);
      } else {
        await fetchQuote();
      }
      toast.success('Payment schedule set.');
    } catch (err) {
      toast.error(
        err instanceof Error
          ? err.message
          : 'Error setting payment schedule'
      );
    } finally {
      setLoading(false);
    }
  };

  const handleSend = async () => {
    const confirmed = await requestConfirmation(
      'Send quote?',
      'Are you sure you want to send this quote?',
      'Send'
    );
    if (!confirmed) return;
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}/send`, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        throw new Error((data && data.error) || 'Failed to send quote');
      }
      if (data) setQuote(data);
      toast.success('Quote sent.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Error sending quote');
    } finally {
      setLoading(false);
    }
  };

  const syncingScheduleRef = useRef(false);
  const [isEditingSchedule, setIsEditingSchedule] = useState(false);
  const [scheduleDraft, setScheduleDraft] = useState<
    {
      id: number;
      title: string;
      amount: number;
      dueDate: string | null;
      isPaid: boolean;
      percent?: number;
      percentInput?: string;
      source?: 'percent' | 'amount';
    }[]
  >([]);

  // Auto-sync payment schedule: create/update the default "Full Payment" milestone
  useEffect(() => {
    if (!quote || quote.state !== 'draft' || syncingScheduleRef.current || isEditingSchedule) return;

    const { quoteTotal } = computeQuoteTotals(quote);
    const schedule = quote.paymentSchedule || [];

    // Determine if schedule is the auto-generated default
    const isAuto = schedule.length === 1 && schedule[0].title === 'Full Payment';

    const needsCreate = schedule.length === 0 && quoteTotal > 0;
    const needsUpdate = isAuto && Math.abs(schedule[0].amount - quoteTotal) > 0.01;

    if (!needsCreate && !needsUpdate) return;

    syncingScheduleRef.current = true;

    (async () => {
      try {
        const response = await fetch(
          `${window.petSettings.apiUrl}/quotes/${quoteId}/payment-schedule`,
          {
            method: 'POST',
            headers: {
              'X-WP-Nonce': window.petSettings.nonce,
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              milestones: [{ title: 'Full Payment', amount: quoteTotal, dueDate: null }],
            }),
          }
        );
        const data = await response.json().catch(() => null);
        if (response.ok && data) {
          setQuote(data);
        }
      } catch (_) {
        // Silent failure for auto-sync
      } finally {
        syncingScheduleRef.current = false;
      }
    })();
  }, [quote]);

  useEffect(() => {
    if (quote && Array.isArray(quote.paymentSchedule)) {
      const { quoteTotal } = computeQuoteTotals(quote);

      const nonBalance = quote.paymentSchedule.filter(
        (m: any) => m.title !== 'Balance on completion'
      );
      const existingBalance = quote.paymentSchedule.find(
        (m: any) => m.title === 'Balance on completion'
      );

      const nonBalanceWithDerived = nonBalance.map((m: any) => {
        let percent: number | undefined = undefined;
        if (quoteTotal > 0 && typeof m.amount === 'number') {
          percent = (m.amount / quoteTotal) * 100;
        }
        const percentInput =
          typeof percent === 'number' && Number.isFinite(percent)
            ? percent.toFixed(2)
            : '';
        return {
          ...m,
          percent,
          percentInput,
          source: 'amount' as const,
        };
      });

      const nonBalanceTotal = nonBalance.reduce(
        (sum: number, m: any) =>
          sum + (typeof m.amount === 'number' ? m.amount : 0),
        0
      );
      const balanceAmount = quoteTotal - nonBalanceTotal;

      const balancePercent =
        quoteTotal > 0 ? (balanceAmount / quoteTotal) * 100 : undefined;
      const balancePercentInput =
        typeof balancePercent === 'number' && Number.isFinite(balancePercent)
          ? balancePercent.toFixed(2)
          : '';

      const balanceRow: any = {
        id: existingBalance?.id ?? Date.now(),
        title: 'Balance on completion',
        amount: balanceAmount,
        dueDate: existingBalance?.dueDate ?? null,
        isPaid: existingBalance?.isPaid ?? false,
        percent: balancePercent,
        percentInput: balancePercentInput,
        source: 'amount' as const,
      };

      setScheduleDraft([...nonBalanceWithDerived, balanceRow]);
      setIsEditingSchedule(false);
    } else {
      setScheduleDraft([]);
      setIsEditingSchedule(false);
    }
  }, [quote?.paymentSchedule]);

  const handleAccept = async () => {
    const confirmed = await requestConfirmation(
      'Accept quote?',
      'Are you sure you want to mark this quote as ACCEPTED? This will create a project.',
      'Accept'
    );
    if (!confirmed) return;
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}/accept`, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        throw new Error((data && data.error) || 'Failed to accept quote');
      }
      if (data) setQuote(data);
      toast.success('Quote accepted.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Error accepting quote');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmitForApproval = async () => {
    const confirmed = await requestConfirmation(
      'Submit for approval?',
      'This will send the quote to a manager for review before it can be sent to the customer.',
      'Submit'
    );
    if (!confirmed) return;
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}/submit-for-approval`, {
        method: 'POST',
        headers: { 'X-WP-Nonce': window.petSettings.nonce },
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error((data && data.error) || 'Failed to submit for approval');
      if (data) setQuote(data);
      toast.success('Quote submitted for manager approval.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Error submitting for approval');
    } finally {
      setLoading(false);
    }
  };

  const handleApproveQuote = async () => {
    const confirmed = await requestConfirmation(
      'Approve quote?',
      'This will approve the quote and allow it to be sent to the customer.',
      'Approve'
    );
    if (!confirmed) return;
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}/approve`, {
        method: 'POST',
        headers: { 'X-WP-Nonce': window.petSettings.nonce },
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error((data && data.error) || 'Failed to approve quote');
      if (data) setQuote(data);
      toast.success('Quote approved — ready to send.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Error approving quote');
    } finally {
      setLoading(false);
    }
  };

  const handleRejectApproval = async () => {
    if (!rejectNoteInput.trim()) {
      toast.error('Please enter a rejection note so the sales person knows what to fix.');
      return;
    }
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}/reject-approval`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.petSettings.nonce,
        },
        body: JSON.stringify({ note: rejectNoteInput }),
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error((data && data.error) || 'Failed to reject approval');
      if (data) setQuote(data);
      setShowRejectForm(false);
      setRejectNoteInput('');
      toast.success('Quote returned to draft with rejection note.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Error rejecting quote');
    } finally {
      setLoading(false);
    }
  };

  const handleAdjustmentAdded = (quoteData?: any) => {
    setShowAdjustmentForm(false);
    if (quoteData && typeof quoteData === 'object') {
      setQuote(quoteData);
    }
  };

  const handleRemoveAdjustment = async (adjustmentId: number) => {
    const confirmed = await requestConfirmation(
      'Remove adjustment?',
      'Are you sure you want to remove this adjustment?',
      'Remove'
    );
    if (!confirmed) return;
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}/adjustments/${adjustmentId}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        throw new Error((data && data.error) || 'Failed to remove adjustment');
      }
      if (data) setQuote(data);
      toast.success('Adjustment removed.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Error removing adjustment');
    } finally {
      setLoading(false);
    }
  };

  const handleOpenBlockTypeSelection = (sectionId: number | null) => {
    setBlockSectionIdForCreate(sectionId);
    setShowTypeSelection(true);
  };

  const handleCancelBlockTypeSelection = () => {
    setShowTypeSelection(false);
    setBlockSectionIdForCreate(null);
  };

  const handleCreateBlockForType = async (
    blockType: string,
    sectionIdOverride?: number | null
  ) => {
    const targetSectionId =
      typeof sectionIdOverride !== 'undefined'
        ? sectionIdOverride
        : blockSectionIdForCreate;

    try {
      const response = await fetch(
        `${window.petSettings.apiUrl}/quotes/${quoteId}/sections/${targetSectionId}/blocks`,
        {
          method: 'POST',
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            type: blockType,
            payload: {},
          }),
        }
      );

      const payload = await response.json().catch(() => null);

      if (!response.ok) {
        const message =
          payload && typeof payload.error === 'string'
            ? payload.error
            : 'Failed to add block';
        throw new Error(message);
      }

      if (payload && typeof payload === 'object') {
        setQuote(payload);

        const blocks = Array.isArray((payload as any).blocks)
          ? (payload as any).blocks
          : [];
        const sectionBlocks =
          targetSectionId === null
            ? blocks.filter(
                (block: any) =>
                  block.sectionId === null && block.type === blockType
              )
            : blocks.filter(
                (block: any) => block.sectionId === targetSectionId
              );

        if (sectionBlocks.length > 0) {
          const newBlock = sectionBlocks.reduce(
            (current: any | null, block: any) => {
              if (!current) {
                return block;
              }
              return block.id > current.id ? block : current;
            },
            null as any | null
          );

          if (newBlock) {
            openBlockEditor(newBlock);
          }
        }
      } else {
        await fetchQuote();
      }

      setShowTypeSelection(false);
      setBlockSectionIdForCreate(null);
      toast.success('Block added.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Error adding block');
    }
  };

  const handleDeleteBlock = async (blockId: number) => {
    const confirmed = await requestConfirmation(
      'Delete block?',
      'Are you sure you want to delete this block?',
      'Delete'
    );
    if (!confirmed) {
      return;
    }

    try {
      const response = await fetch(
        `${window.petSettings.apiUrl}/quotes/${quoteId}/blocks/${blockId}`,
        {
          method: 'DELETE',
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
          },
        }
      );

      const payload = await response.json().catch(() => null);

      if (!response.ok) {
        const message =
          payload && typeof payload.error === 'string'
            ? payload.error
            : 'Failed to delete block';
        throw new Error(message);
      }

      if (payload && typeof payload === 'object') setQuote(payload);
      toast.success('Block deleted.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Error deleting block');
    }
  };

  const handleSectionNameClick = (sectionId: number, currentName: string) => {
    setEditingSectionId(sectionId);
    setSectionDraftNames((prev) => ({
      ...prev,
      [sectionId]: currentName,
    }));
  };

  const handleSectionNameChange = (sectionId: number, value: string) => {
    setSectionDraftNames((prev) => ({
      ...prev,
      [sectionId]: value,
    }));
  };

  const handleSectionNameBlur = async (sectionId: number) => {
    const draftName = sectionDraftNames[sectionId];
    const section = sectionsForRendering.find((s) => s.id === sectionId);
    if (!section) {
      setEditingSectionId(null);
      return;
    }

    const nameToSave = draftName && draftName.trim() !== '' ? draftName.trim() : section.name;

    if (nameToSave === section.name) {
      setEditingSectionId(null);
      return;
    }

    try {
      const response = await fetch(
        `${window.petSettings.apiUrl}/quotes/${quoteId}/sections/${sectionId}`,
        {
          method: 'PATCH',
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            name: nameToSave,
            showTotalValue: section.showTotalValue,
            showItemCount: section.showItemCount,
            showTotalHours: section.showTotalHours,
          }),
        }
      );

      const payload = await response.json().catch(() => null);

      if (!response.ok) {
        const message =
          payload && typeof payload.error === 'string'
            ? payload.error
            : 'Failed to update section';
        throw new Error(message);
      }

      if (payload && typeof payload === 'object') setQuote(payload);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Error renaming section');
    } finally {
      setEditingSectionId(null);
    }
  };


  const handleSectionToggle = async (sectionId: number, key: 'showTotalValue' | 'showItemCount' | 'showTotalHours') => {
    const section = sectionsForRendering.find((s) => s.id === sectionId);
    if (!section) {
      return;
    }

    const updated = {
      showTotalValue: section.showTotalValue,
      showItemCount: section.showItemCount,
      showTotalHours: section.showTotalHours,
    };

    updated[key] = !updated[key];

    try {
      const response = await fetch(
        `${window.petSettings.apiUrl}/quotes/${quoteId}/sections/${sectionId}`,
        {
          method: 'PATCH',
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            name: section.name,
            ...updated,
          }),
        }
      );

      const payload = await response.json().catch(() => null);

      if (!response.ok) {
        const message =
          payload && typeof payload.error === 'string'
            ? payload.error
            : 'Failed to update section toggles';
        throw new Error(message);
      }

      if (payload && typeof payload === 'object') setQuote(payload);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Error updating section settings');
    }
  };

  const handleCloneSection = async (sectionId: number) => {
    try {
      const response = await fetch(
        `${window.petSettings.apiUrl}/quotes/${quoteId}/sections/${sectionId}/clone`,
        {
          method: 'POST',
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
          },
        }
      );

      const payload = await response.json().catch(() => null);

      if (!response.ok) {
        const message =
          payload && typeof payload.error === 'string'
            ? payload.error
            : 'Failed to clone section';
        throw new Error(message);
      }

      if (payload && typeof payload === 'object') setQuote(payload);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Error cloning section');
    }
  };

  const handleDeleteSection = async (sectionId: number, hasNonTextBlocks: boolean) => {
    if (hasNonTextBlocks) {
      toast.error('Cannot delete a section that contains non-text blocks.');
      return;
    }
    const confirmed = await requestConfirmation(
      'Delete section?',
      'Are you sure you want to delete this empty section?',
      'Delete'
    );
    if (!confirmed) {
      return;
    }

    try {
      const response = await fetch(
        `${window.petSettings.apiUrl}/quotes/${quoteId}/sections/${sectionId}`,
        {
          method: 'DELETE',
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
          },
        }
      );

      const payload = await response.json().catch(() => null);

      if (!response.ok) {
        const message =
          payload && typeof payload.error === 'string'
            ? payload.error
            : 'Failed to delete section';
        throw new Error(message);
      }

      if (payload && typeof payload === 'object') setQuote(payload);
      toast.success('Section deleted.');
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Error deleting section');
    }
  };

  const openBlockEditor = (block: QuoteBlock) => {
    setExpandedBlockId(block.id);
    setBlockDrafts((prev) => {
      const payload = block.payload || {};

      const draft: any = {
        description: payload.description ?? '',
        quantity: payload.quantity ?? 1,
        sellValue: payload.sellValue ?? payload.totalValue ?? 0,
        owner: payload.owner ?? '',
        team: payload.team ?? '',
        ownerType: payload.ownerType ?? '',
        ownerId: payload.ownerId ?? null,
        teamId: payload.teamId ?? null,
        totalValue: payload.totalValue ?? payload.sellValue ?? 0,
        unitCost: payload.unitCost ?? null,
        totalCost: payload.totalCost ?? null,
        type: block.type,
      };

      if (block.type === 'PriceAdjustmentBlock') {
        draft.description = payload.description ?? '';
        draft.amount = payload.amount ?? 0;
      } else if (block.type === 'TextBlock') {
        draft.text = payload.text ?? '';
      } else if (block.type === 'HardwareBlock') {
        draft.catalogItemId = payload.catalogItemId ?? null;
        draft.unitPrice =
          payload.unitPrice ??
          payload.unit_price ??
          payload.sellValue ??
          0;
        draft.unitCost = payload.unitCost ?? null;
      } else if (block.type === 'OnceOffSimpleServiceBlock') {
        draft.catalogItemId = payload.catalogItemId ?? null;
        draft.roleId = payload.roleId ?? null;
        draft.unit = payload.unit ?? 'hours';
        draft.price_override = payload.price_override ?? false;
        draft.unitCost = payload.unitCost ?? null;
      } else if (block.type === 'OnceOffProjectBlock') {
        draft.phases = Array.isArray(payload.phases) ? payload.phases : [];
      }

      return {
        ...prev,
        [block.id]: draft,
      };
    });
  };

  const updateBlockDraft = (blockId: number, field: string, value: any) => {
    setBlockDrafts((prev) => ({
      ...prev,
      [blockId]: {
        ...(prev[blockId] || {}),
        [field]: value,
      },
    }));
  };

  const saveBlock = async (block: QuoteBlock) => {
    const draft = blockDrafts[block.id];
    if (!draft) {
      return;
    }

    let normalizedPayload: any;

    if (block.type === 'OnceOffSimpleServiceBlock') {
      const quantity = Number.isFinite(Number(draft.quantity))
        ? Number(draft.quantity)
        : 1;
      const sellValue = Number.isFinite(Number(draft.sellValue))
        ? Number(draft.sellValue)
        : 0;
      const unitCost = Number.isFinite(Number(draft.unitCost))
        ? Number(draft.unitCost)
        : null;
      const totalValue = quantity * sellValue;
      const totalCost = unitCost !== null
        ? unitCost * quantity
        : Number.isFinite(Number(draft.totalCost))
        ? Number(draft.totalCost)
        : null;
      normalizedPayload = {
        description: draft.description ?? '',
        quantity,
        sellValue,
        totalValue,
        ownerType: draft.ownerType ?? '',
        ownerId:
          typeof draft.ownerId === 'number' && Number.isFinite(draft.ownerId)
            ? draft.ownerId
            : null,
        teamId:
          typeof draft.teamId === 'number' && Number.isFinite(draft.teamId)
            ? draft.teamId
            : null,
        owner: draft.owner ?? '',
        team: draft.team ?? '',
        catalogItemId:
          draft.catalogItemId !== undefined ? draft.catalogItemId : null,
        roleId:
          typeof draft.roleId === 'number' && Number.isFinite(draft.roleId)
            ? draft.roleId
            : null,
        ...(unitCost !== null ? { unitCost } : {}),
        ...(totalCost !== null ? { totalCost } : {}),
      };
    } else if (block.type === 'OnceOffProjectBlock') {
      const rawPhases = Array.isArray(draft.phases) ? draft.phases : [];
      let totalCost: number | null = 0;

      const phases = rawPhases.map((phase: any, index: number) => {
        const phaseId =
          typeof phase.id === 'string' && phase.id.length > 0
            ? phase.id
            : generateLocalId();

        const unitsRaw = Array.isArray(phase.units) ? phase.units : [];
        let phaseTotalValue = 0;
        let phaseTotalCost: number | null = 0;

        const units = unitsRaw.map((unit: any, unitIndex: number) => {
          const unitId =
            typeof unit.id === 'string' && unit.id.length > 0
              ? unit.id
              : generateLocalId();
          const quantity = Number.isFinite(Number(unit.quantity))
            ? Number(unit.quantity)
            : 0;
          const unitPrice = Number.isFinite(Number(unit.unitPrice))
            ? Number(unit.unitPrice)
            : 0;
          const totalValue = quantity * unitPrice;
          phaseTotalValue += totalValue;
          const unitCost = Number.isFinite(Number(unit.unitCost))
            ? Number(unit.unitCost)
            : null;
          const totalCost = unitCost !== null
            ? unitCost * quantity
            : Number.isFinite(Number(unit.totalCost))
            ? Number(unit.totalCost)
            : null;
          if (phaseTotalCost !== null) {
            if (totalCost === null) {
              phaseTotalCost = null;
            } else {
              phaseTotalCost += totalCost;
            }
          }

          return {
            id: unitId,
            description: unit.description ?? '',
            quantity,
            unitPrice,
            totalValue,
            catalogItemId:
              unit.catalogItemId !== undefined ? unit.catalogItemId : null,
            ownerType: unit.ownerType ?? '',
            ownerId:
              typeof unit.ownerId === 'number' &&
              Number.isFinite(unit.ownerId)
                ? unit.ownerId
                : null,
            teamId:
              typeof unit.teamId === 'number' && Number.isFinite(unit.teamId)
                ? unit.teamId
                : null,
            owner: unit.owner ?? '',
            team: unit.team ?? '',
            roleId:
              typeof unit.roleId === 'number' && Number.isFinite(unit.roleId)
                ? unit.roleId
                : null,
            ...(unitCost !== null ? { unitCost } : {}),
            ...(totalCost !== null ? { totalCost } : {}),
          };
        });

        const phasePayload: Record<string, any> = {
          id: phaseId,
          name: phase.name ?? '',
          order: typeof phase.order === 'number' ? phase.order : index,
          units,
          phaseTotalValue,
        };
        if (phaseTotalCost !== null) {
          phasePayload.phaseTotalCost = phaseTotalCost;
        } else if (Number.isFinite(Number(phase.phaseTotalCost))) {
          phasePayload.phaseTotalCost = Number(phase.phaseTotalCost);
        }

        const phaseCostForTotal = typeof phasePayload.phaseTotalCost === 'number'
          ? phasePayload.phaseTotalCost
          : null;
        if (totalCost !== null) {
          if (phaseCostForTotal === null) {
            totalCost = null;
          } else {
            totalCost += phaseCostForTotal;
          }
        }

        return phasePayload;
      });

      const totalValue = phases.reduce(
        (sum: number, phase: any) => sum + (phase.phaseTotalValue || 0),
        0
      );

      normalizedPayload = {
        description: draft.description ?? '',
        phases,
        totalValue,
        ...(totalCost !== null
          ? { totalCost }
          : Number.isFinite(Number(draft.totalCost))
          ? { totalCost: Number(draft.totalCost) }
          : {}),
      };
    } else if (block.type === 'HardwareBlock') {
      const quantity = Number.isFinite(Number(draft.quantity))
        ? Number(draft.quantity)
        : 1;
      const unitPrice = Number.isFinite(Number(draft.unitPrice))
        ? Number(draft.unitPrice)
        : 0;
      const unitCost = Number.isFinite(Number(draft.unitCost))
        ? Number(draft.unitCost)
        : null;
      const totalValue = quantity * unitPrice;
      const totalCost = unitCost !== null ? unitCost * quantity : null;
      normalizedPayload = {
        catalogItemId:
          draft.catalogItemId !== undefined ? draft.catalogItemId : null,
        description: draft.description ?? '',
        quantity,
        unitPrice,
        totalValue,
        ...(unitCost !== null ? { unitCost } : {}),
        ...(totalCost !== null ? { totalCost } : {}),
      };
    } else if (block.type === 'PriceAdjustmentBlock') {
      const amount = Number.isFinite(Number(draft.amount)) ? Number(draft.amount) : 0;
      normalizedPayload = {
        description: draft.description ?? '',
        amount,
      };
    } else if (block.type === 'TextBlock') {
      normalizedPayload = {
        text: draft.text ?? '',
      };
    } else {
      normalizedPayload = block.payload || {};
    }

    try {
      setSavingBlockId(block.id);
      const response = await fetch(
        `${window.petSettings.apiUrl}/quotes/${quoteId}/blocks/${block.id}`,
        {
          method: 'PATCH',
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ payload: normalizedPayload }),
        }
      );

      const payload = await response.json().catch(() => null);

      if (!response.ok) {
        const message =
          payload && typeof payload.error === 'string'
            ? payload.error
            : 'Failed to update block';
        throw new Error(message);
      }

      // Store snapshot of previous payload before updating quote
      setBlockSnapshots((prev) => ({
        ...prev,
        [block.id]: { ...(block.payload || {}) },
      }));
      setServerErrors((prev) => {
        const next = { ...prev };
        delete next[block.id];
        return next;
      });
      setExpandedBlockId(null);
      setSavingBlockId(null);
      // For always-expanded project blocks, clear draft so useEffect re-inits from updated payload
      if (block.type === 'OnceOffProjectBlock') {
        setBlockDrafts((prev) => {
          const next = { ...prev };
          delete next[block.id];
          return next;
        });
      }
      if (payload && typeof payload === 'object') setQuote(payload);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Error updating block';
      setServerErrors((prev) => ({ ...prev, [block.id]: message }));
      setSavingBlockId(null);
      // Keep row in edit mode — do NOT close expandedBlockId
    }
  };

  const cancelBlockEdit = (blockId: number) => {
    setExpandedBlockId((current) => (current === blockId ? null : current));
    setBlockDrafts((prev) => {
      const next = { ...prev };
      delete next[blockId];
      return next;
    });
    setServerErrors((prev) => {
      const next = { ...prev };
      delete next[blockId];
      return next;
    });
  };

  const handleMoveSection = async (sectionId: number, adjacentSectionId: number) => {
    const sections = quote?.sections || [];
    const sectionA = sections.find((s) => s.id === sectionId);
    const sectionB = sections.find((s) => s.id === adjacentSectionId);
    if (!sectionA || !sectionB) return;

    const changes = [
      { id: sectionA.id, orderIndex: sectionB.orderIndex },
      { id: sectionB.id, orderIndex: sectionA.orderIndex },
    ];

    // Optimistic update
    setQuote((prev) => {
      if (!prev) return prev;
      return {
        ...prev,
        sections: (prev.sections || []).map((s) => {
          if (s.id === sectionA.id) return { ...s, orderIndex: sectionB.orderIndex };
          if (s.id === sectionB.id) return { ...s, orderIndex: sectionA.orderIndex };
          return s;
        }),
      };
    });

    try {
      const response = await fetch(
        `${window.petSettings.apiUrl}/quotes/${quoteId}/sections/reorder`,
        {
          method: 'POST',
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ changes }),
        }
      );
      if (!response.ok) {
        await fetchQuote();
      }
    } catch (err) {
      console.error('Failed to reorder sections', err);
      await fetchQuote();
    }
  };

  const handleMoveBlock = async (blockId: number, adjacentBlockId: number) => {
    const blocks = quote?.blocks || [];
    const blockA = blocks.find((b) => b.id === blockId);
    const blockB = blocks.find((b) => b.id === adjacentBlockId);
    if (!blockA || !blockB) return;

    const changes = [
      { id: blockA.id, orderIndex: blockB.orderIndex, sectionId: blockA.sectionId },
      { id: blockB.id, orderIndex: blockA.orderIndex, sectionId: blockB.sectionId },
    ];

    // Optimistic update
    setQuote((prev) => {
      if (!prev) return prev;
      return {
        ...prev,
        blocks: (prev.blocks || []).map((b) => {
          if (b.id === blockA.id) return { ...b, orderIndex: blockB.orderIndex };
          if (b.id === blockB.id) return { ...b, orderIndex: blockA.orderIndex };
          return b;
        }),
      };
    });

    try {
      const response = await fetch(
        `${window.petSettings.apiUrl}/quotes/${quoteId}/blocks/reorder`,
        {
          method: 'POST',
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ changes }),
        }
      );
      if (!response.ok) {
        await fetchQuote();
      }
    } catch (err) {
      console.error('Failed to reorder blocks', err);
      await fetchQuote();
    }
  };

  const revertBlock = (blockId: number) => {
    const snapshot = blockSnapshots[blockId];
    if (!snapshot) return;
    // Restore the snapshot into the block's payload locally
    setQuote((prev) => {
      if (!prev || !prev.blocks) return prev;
      return {
        ...prev,
        blocks: prev.blocks.map((b) =>
          b.id === blockId ? { ...b, payload: { ...snapshot } } : b
        ),
      };
    });
    // Clear the snapshot (one level only)
    setBlockSnapshots((prev) => {
      const next = { ...prev };
      delete next[blockId];
      return next;
    });
    cancelBlockEdit(blockId);
  };

  const updateProjectDraft = (
    blockId: number,
    updater: (draft: any) => any
  ) => {
    setBlockDrafts((prev) => {
      const current = prev[blockId] || {};
      const next = updater({ ...current });
      return {
        ...prev,
        [blockId]: next,
      };
    });
  };

  const addProjectPhase = (blockId: number) => {
    updateProjectDraft(blockId, (draft) => {
      const phases = Array.isArray(draft.phases) ? [...draft.phases] : [];
      phases.push({
        id: generateLocalId(),
        name: '',
        order: phases.length,
        units: [],
        phaseTotalValue: 0,
      });
      draft.phases = phases;
      return draft;
    });
  };

  const removeProjectPhase = (blockId: number, phaseIndex: number) => {
    updateProjectDraft(blockId, (draft) => {
      const phases = Array.isArray(draft.phases) ? [...draft.phases] : [];
      if (phaseIndex < 0 || phaseIndex >= phases.length) {
        return draft;
      }
      phases.splice(phaseIndex, 1);
      draft.phases = phases.map((phase: any, index: number) => ({
        ...phase,
        order: index,
      }));
      return draft;
    });
  };

  const moveProjectPhase = (
    blockId: number,
    phaseIndex: number,
    direction: -1 | 1
  ) => {
    updateProjectDraft(blockId, (draft) => {
      const phases = Array.isArray(draft.phases) ? [...draft.phases] : [];
      const targetIndex = phaseIndex + direction;
      if (
        phaseIndex < 0 ||
        phaseIndex >= phases.length ||
        targetIndex < 0 ||
        targetIndex >= phases.length
      ) {
        return draft;
      }
      const temp = phases[phaseIndex];
      phases[phaseIndex] = phases[targetIndex];
      phases[targetIndex] = temp;
      draft.phases = phases.map((phase: any, index: number) => ({
        ...phase,
        order: index,
      }));
      return draft;
    });
  };

  const addProjectUnit = (blockId: number, phaseIndex: number) => {
    updateProjectDraft(blockId, (draft) => {
      const phases = Array.isArray(draft.phases) ? [...draft.phases] : [];
      if (phaseIndex < 0 || phaseIndex >= phases.length) {
        return draft;
      }
      const phase = { ...phases[phaseIndex] };
      const units = Array.isArray(phase.units) ? [...phase.units] : [];
      units.push({
        id: generateLocalId(),
        description: '',
        quantity: 1,
        unitPrice: 0,
        totalValue: 0,
      });
      phase.units = units;
      phases[phaseIndex] = phase;
      draft.phases = phases;
      return draft;
    });
  };

  const updateProjectUnitField = (
    blockId: number,
    phaseIndex: number,
    unitIndex: number,
    field: 'description' | 'quantity' | 'unitPrice'
  ) => (value: string) => {
    updateProjectDraft(blockId, (draft) => {
      const phases = Array.isArray(draft.phases) ? [...draft.phases] : [];
      if (phaseIndex < 0 || phaseIndex >= phases.length) {
        return draft;
      }
      const phase = { ...phases[phaseIndex] };
      const units = Array.isArray(phase.units) ? [...phase.units] : [];
      if (unitIndex < 0 || unitIndex >= units.length) {
        return draft;
      }
      const unit = { ...units[unitIndex] };
      if (field === 'description') {
        unit.description = value;
      } else if (field === 'quantity') {
        unit.quantity = Number(value);
      } else if (field === 'unitPrice') {
        unit.unitPrice = Number(value);
      }
      const quantity = Number.isFinite(Number(unit.quantity))
        ? Number(unit.quantity)
        : 0;
      const unitPrice = Number.isFinite(Number(unit.unitPrice))
        ? Number(unit.unitPrice)
        : 0;
      unit.totalValue = quantity * unitPrice;
      units[unitIndex] = unit;
      phase.units = units;
      phase.phaseTotalValue = units.reduce(
        (sum: number, u: any) => sum + (u.totalValue || 0),
        0
      );
      phases[phaseIndex] = phase;
      draft.phases = phases;
      return draft;
    });
  };

  const removeProjectUnit = (
    blockId: number,
    phaseIndex: number,
    unitIndex: number
  ) => {
    updateProjectDraft(blockId, (draft) => {
      const phases = Array.isArray(draft.phases) ? [...draft.phases] : [];
      if (phaseIndex < 0 || phaseIndex >= phases.length) {
        return draft;
      }
      const phase = { ...phases[phaseIndex] };
      const units = Array.isArray(phase.units) ? [...phase.units] : [];
      if (unitIndex < 0 || unitIndex >= units.length) {
        return draft;
      }
      units.splice(unitIndex, 1);
      phase.units = units;
      phase.phaseTotalValue = units.reduce(
        (sum: number, u: any) => sum + (u.totalValue || 0),
        0
      );
      phases[phaseIndex] = phase;
      draft.phases = phases;
      return draft;
    });
  };

  const moveProjectUnit = (
    blockId: number,
    phaseIndex: number,
    unitIndex: number,
    direction: -1 | 1
  ) => {
    updateProjectDraft(blockId, (draft) => {
      const phases = Array.isArray(draft.phases) ? [...draft.phases] : [];
      if (phaseIndex < 0 || phaseIndex >= phases.length) {
        return draft;
      }
      const phase = { ...phases[phaseIndex] };
      const units = Array.isArray(phase.units) ? [...phase.units] : [];
      const targetIndex = unitIndex + direction;
      if (
        unitIndex < 0 ||
        unitIndex >= units.length ||
        targetIndex < 0 ||
        targetIndex >= units.length
      ) {
        return draft;
      }
      const temp = units[unitIndex];
      units[unitIndex] = units[targetIndex];
      units[targetIndex] = temp;
      phase.units = units;
      phase.phaseTotalValue = units.reduce(
        (sum: number, u: any) => sum + (u.totalValue || 0),
        0
      );
      phases[phaseIndex] = phase;
      draft.phases = phases;
      return draft;
    });
  };

  const handleAddSection = async () => {
    try {
      const response = await fetch(
        `${window.petSettings.apiUrl}/quotes/${quoteId}/sections`,
        {
          method: 'POST',
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ name: 'New Section' }),
        }
      );
      const payload = await response.json().catch(() => null);
      if (!response.ok) {
        const message =
          payload && typeof payload.error === 'string'
            ? payload.error
            : 'Failed to add section';
        throw new Error(message);
      }
      if (payload && typeof payload === 'object') setQuote(payload);
    } catch (err) {
      toast.error(
        err instanceof Error ? err.message : 'Error adding section'
      );
    }
  };

  const handleAddTextSectionWithBlock = async () => {
    try {
      const response = await fetch(
        `${window.petSettings.apiUrl}/quotes/${quoteId}/sections`,
        {
          method: 'POST',
          headers: {
            'X-WP-Nonce': window.petSettings.nonce,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ name: 'Text' }),
        }
      );

      const payload = await response.json().catch(() => null);

      if (!response.ok) {
        const message =
          payload && typeof payload.error === 'string'
            ? payload.error
            : 'Failed to add text section';
        throw new Error(message);
      }

      if (payload && typeof payload === 'object') {
        setQuote(payload);

        const sections = Array.isArray((payload as any).sections)
          ? (payload as any).sections
          : [];

        if (sections.length > 0) {
          const newSection = sections.reduce(
            (current: any | null, section: any) => {
              if (!current) {
                return section;
              }
              const currentIndex =
                typeof current.orderIndex === 'number'
                  ? current.orderIndex
                  : 0;
              const sectionIndex =
                typeof section.orderIndex === 'number'
                  ? section.orderIndex
                  : 0;
              return sectionIndex > currentIndex ? section : current;
            },
            null as any | null
          );

          if (newSection && typeof newSection.id === 'number') {
            await handleCreateBlockForType('TextBlock', newSection.id);
          }
        } else {
          await fetchQuote();
        }
      } else {
        await fetchQuote();
      }
    } catch (err) {
      toast.error(
        err instanceof Error
          ? err.message
          : 'Error adding text section'
      );
    }
  };

  if (loading) return <LoadingState label="Loading quote details…" />;
  if (error) return <ErrorState message={error} onRetry={fetchQuote} />;
  if (!quote) return <EmptyState message="Quote not found." />;

  const { quoteTotal, sectionTotals } = computeQuoteTotals(quote);

  const totalInternalCost =
    quote.totalInternalCost ??
    (quote.components || []).reduce((sum, c) => sum + (c.internalCost || 0), 0);
  const adjustedCost = quote.adjustedTotalInternalCost ?? totalInternalCost;
  const margin = quote.margin ?? (quoteTotal - adjustedCost);

  const blocks = Array.isArray((quote as any).blocks)
    ? (quote as any).blocks
    : [];

  const hasPricedBlocks = blocks.some(
    (block: any) => block && block.priced && block.type !== 'TextBlock'
  );

  const hasDraftSchedule = isEditingSchedule && scheduleDraft.length > 0;
  const scheduleForSummary = hasDraftSchedule
    ? scheduleDraft
    : quote.paymentSchedule || [];
  const scheduledTotal = hasDraftSchedule
    ? quoteTotal
    : scheduleForSummary.reduce((sum, milestone) => {
        const rawAmount =
          typeof milestone.amount === 'number'
            ? milestone.amount
            : Number(milestone.amount);
        const amount = Number.isFinite(rawAmount) ? rawAmount : 0;
        return sum + amount;
      }, 0);
  const paymentScheduleDelta = scheduledTotal - quoteTotal;

  const schedule = Array.isArray((quote as any).paymentSchedule)
    ? (quote as any).paymentSchedule
    : [];

  const readinessIssues: string[] = [];
  if (!hasPricedBlocks)
    readinessIssues.push('At least one priced component is required');
  if (margin < 0) readinessIssues.push('Margin cannot be negative');
  if (!quote.title) readinessIssues.push('Title is required');

  if (schedule.length === 0) {
    readinessIssues.push('Payment schedule is required');
  } else if (!isEditingSchedule && Math.abs(paymentScheduleDelta) > 0.01) {
    readinessIssues.push('Payment schedule must match quote total');
  }

  const isReady = readinessIssues.length === 0;

  const qa = quote as any;
  const quoteHealthResult = computeQuoteHealth({
    state: quote.state,
    totalValue: quoteTotal,
    createdAt: qa.createdAt || new Date().toISOString(),
    updatedAt: qa.updatedAt || null,
  });

  const customerName = customers.find(c => c.id === quote.customerId)?.name || `Customer #${quote.customerId}`;

  return (
    <div className={`pet-quote-details ${quoteHealthResult.className}`}>
      <div style={{ marginBottom: '16px' }}>
        <button className="button" onClick={onBack}>&larr; Back to Quotes</button>
      </div>

      {/* Top bar: title + state + actions */}
      <div className="pet-quote-topbar">
        <h2>
          {editingHeaderField === 'title' ? (
            <input
              type="text"
              className="regular-text"
              value={headerDraftTitle}
              onChange={(e) => setHeaderDraftTitle(e.target.value)}
              onBlur={saveHeaderField}
              onKeyDown={(e) => { if (e.key === 'Enter') saveHeaderField(); if (e.key === 'Escape') setEditingHeaderField(null); }}
              autoFocus
              style={{ fontSize: 'inherit', fontWeight: 'inherit' }}
            />
          ) : (
            <span
              onClick={() => startEditingHeader('title')}
              className={quote.state === 'draft' ? 'pet-editable-text' : undefined}
              title={quote.state === 'draft' ? 'Click to edit title' : undefined}
            >
              {quote.title || '(untitled)'}
            </span>
          )}
        </h2>
        <span className={`pet-status-badge status-${quote.state.toLowerCase()}`}>
          {quote.state}
        </span>
        {quoteHealthResult.reasons.map((r, i) => (
          <span key={i} className={`uhb-tag uhb-tag-${r.color}`}>{r.label}</span>
        ))}
        <div className="pet-quote-topbar-actions">
          {quote.state === 'draft' && (
            <>
              <button className="button" onClick={handleSaveDraft} disabled={loading}>Save Draft</button>
              {quote.approvalState?.requiresApprovalForSend ? (
                <button
                  className="button button-primary"
                  onClick={handleSubmitForApproval}
                  disabled={!isReady || loading}
                  title={!isReady ? readinessIssues.join('\n') : 'Submit to manager for approval before sending'}
                >
                  Submit for Approval
                </button>
              ) : (
                <button
                  className="button button-primary"
                  onClick={handleSend}
                  disabled={!isReady || loading}
                  title={!isReady ? readinessIssues.join('\n') : 'Send to customer'}
                >
                  Send Quote
                </button>
              )}
            </>
          )}
          {quote.state === 'pending_approval' && (
            <>
              <span style={{ color: '#856404', background: '#fff3cd', border: '1px solid #ffc107', padding: '4px 12px', borderRadius: '4px', fontSize: '13px', fontWeight: 500 }}>
                ⏳ Awaiting Manager Approval
              </span>
              <button className="button button-primary" onClick={handleApproveQuote} disabled={loading}>
                Approve
              </button>
              {!showRejectForm && (
                <button className="button" onClick={() => setShowRejectForm(true)} disabled={loading}>
                  Reject
                </button>
              )}
            </>
          )}
          {quote.state === 'approved' && (
            <button
              className="button button-primary"
              onClick={handleSend}
              disabled={!isReady || loading}
              title={!isReady ? readinessIssues.join('\n') : 'Send approved quote to customer'}
            >
              Send Quote
            </button>
          )}
          {quote.state === 'sent' && (
            <button className="button button-primary" onClick={handleAccept}>Accept Quote</button>
          )}
          {(() => {
            const cs = quoteConvStatuses.get(String(quoteId));
            const dotColor = cs && cs.status !== 'none'
              ? ({ red: '#dc3545', amber: '#f0ad4e', green: '#28a745', blue: '#007bff' }[cs.status] || undefined)
              : undefined;
            return (
              <button
                className="button"
                onClick={() => openConversation({
                  contextType: 'quote',
                  contextId: quote.id.toString(),
                  contextVersion: quote.version.toString(),
                  subject: `Quote #${quote.id}: ${quote.title}`,
                  subjectKey: `quote:${quote.id}`
                })}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}
              >
                {dotColor && <span style={{ display: 'inline-block', width: 10, height: 10, borderRadius: '50%', background: dotColor }} />}
                Discuss
              </button>
            );
          })()}
        </div>
      </div>

      {/* Inline reject form — shown when manager clicks Reject in pending_approval */}
      {quote.state === 'pending_approval' && showRejectForm && (
        <div style={{ margin: '12px 0', padding: '14px 16px', background: '#fff8f0', border: '1px solid #f5c6cb', borderRadius: '4px' }}>
          <strong style={{ display: 'block', marginBottom: '8px', color: '#842029' }}>Rejection note</strong>
          <p style={{ margin: '0 0 8px', fontSize: '13px', color: '#555' }}>
            Explain what needs to change before this quote can be approved. This note will be visible to the sales person.
          </p>
          <textarea
            rows={3}
            style={{ width: '100%', maxWidth: '700px', boxSizing: 'border-box', marginBottom: '8px' }}
            placeholder="e.g. Discount exceeds 20% on line 3 — please reduce or get sign-off from the director first."
            value={rejectNoteInput}
            onChange={(e) => setRejectNoteInput(e.target.value)}
          />
          <div style={{ display: 'flex', gap: '8px' }}>
            <button className="button button-primary" onClick={handleRejectApproval} disabled={loading}>
              Confirm Rejection
            </button>
            <button className="button" onClick={() => { setShowRejectForm(false); setRejectNoteInput(''); }}>
              Cancel
            </button>
          </div>
        </div>
      )}

      {/* Rejection note banner — shown when quote returns to draft after being rejected */}
      {quote.state === 'draft' && quote.approvalState?.rejectionNote && (
        <div style={{ margin: '12px 0', padding: '12px 16px', background: '#f8d7da', border: '1px solid #f5c6cb', borderRadius: '4px', display: 'flex', alignItems: 'flex-start', gap: '10px' }}>
          <span style={{ fontSize: '18px', lineHeight: 1 }}>⚠️</span>
          <div>
            <strong style={{ display: 'block', marginBottom: '4px', color: '#842029' }}>This quote was returned for revision</strong>
            <span style={{ fontSize: '13px', color: '#555' }}>{quote.approvalState.rejectionNote}</span>
          </div>
        </div>
      )}

      {/* Metadata strip */}
      <div className="pet-quote-meta">
        <span><strong>Customer:</strong> {customerName}</span>
        <span><strong>Quote:</strong> #{quote.id}</span>
        <span><strong>Version:</strong> {quote.version}</span>
        <span><strong>Currency:</strong> {quote.currency || 'USD'}</span>
        <span><strong>Components:</strong> {(quote.components || []).length}</span>
      </div>

      {/* KPI cards */}
      <div className="pet-kpi-grid" style={{ position: 'sticky', top: 0, zIndex: 5, background: '#f0f0f1', paddingTop: '8px', paddingBottom: '8px', marginLeft: '-12px', marginRight: '-12px', paddingLeft: '12px', paddingRight: '12px' }}>
        <div className="pet-kpi-card">
          <div className="pet-kpi-value">${quoteTotal.toFixed(2)}</div>
          <div className="pet-kpi-label">Total Value</div>
        </div>
        <div className="pet-kpi-card">
          <div className="pet-kpi-value">${totalInternalCost.toFixed(2)}</div>
          <div className="pet-kpi-label">Base Cost</div>
        </div>
        {quote.costAdjustments && quote.costAdjustments.length > 0 && (
          <div className="pet-kpi-card">
            <div className="pet-kpi-value">${adjustedCost.toFixed(2)}</div>
            <div className="pet-kpi-label">Adjusted Cost</div>
          </div>
        )}
        <div className="pet-kpi-card">
          <div className={`pet-kpi-value ${margin >= 0 ? 'positive' : 'negative'}`}>
            ${margin.toFixed(2)}
          </div>
          <div className="pet-kpi-label">Margin</div>
        </div>
        {!quote.costAdjustments?.length && !showAdjustmentForm && (
          <div className="pet-kpi-card" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <button type="button" className="button button-small" onClick={() => setShowAdjustmentForm(true)}>
              + Cost Adjustment
            </button>
          </div>
        )}
      </div>

      {/* Description */}
      {(quote.description || quote.state === 'draft') && (
        <div className="pet-quote-description">
          <strong>Description: </strong>
          {editingHeaderField === 'description' ? (
            <textarea
              className="large-text"
              rows={3}
              value={headerDraftDescription}
              onChange={(e) => setHeaderDraftDescription(e.target.value)}
              onBlur={saveHeaderField}
              onKeyDown={(e) => { if (e.key === 'Escape') setEditingHeaderField(null); }}
              autoFocus
            />
          ) : (
            <span
              onClick={() => startEditingHeader('description')}
              className={quote.state === 'draft' ? 'pet-editable-text' : undefined}
              title={quote.state === 'draft' ? 'Click to edit description' : undefined}
            >
              {quote.description || '(none)'}
            </span>
          )}
        </div>
      )}

      {/* Readiness notice */}
      {quote.state === 'draft' && !isReady && (
        <div className="pet-readiness-notice">
          <strong>Not ready to send:</strong>
          <ul>
            {readinessIssues.map((issue, i) => <li key={i}>{issue}</li>)}
          </ul>
        </div>
      )}

      {activeSchema && quote.malleableData && (
        <MalleableFieldsRenderer
          schema={activeSchema}
          values={quote.malleableData}
          onChange={() => {}}
          readOnly={true}
        />
      )}

      {/* ── Component Summary (read-only fallback when no sections/blocks exist) ── */}
      {(quote.components || []).length > 0 && sectionsForRendering.length === 0 && (
        <div style={{ marginBottom: '24px' }}>
          <h3>Quote Components</h3>
          {(quote.components || []).map((comp, ci) => {
            const typeLabels: Record<string, string> = {
              implementation: 'Implementation',
              catalog: 'Catalog',
              once_off_service: 'Once-off Service',
              recurring: 'Recurring Service',
            };
            const typeLabel = typeLabels[comp.type] || comp.type;

            return (
              <div key={comp.id ?? ci} style={{ marginBottom: '16px' }}>
                {/* Component header bar */}
                <div
                  style={{
                    background: '#1d2327',
                    color: '#fff',
                    padding: '8px 12px',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                  }}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <span style={{ fontWeight: 600 }}>{comp.section || 'General'}</span>
                    <span style={{
                      fontSize: '11px',
                      background: 'rgba(255,255,255,0.15)',
                      padding: '2px 8px',
                      borderRadius: '3px',
                    }}>
                      {typeLabel}
                    </span>
                    {comp.description && (
                      <span style={{ fontSize: '12px', opacity: 0.7 }}>{comp.description}</span>
                    )}
                  </div>
                  <span style={{ fontWeight: 600 }}>${comp.sellValue.toFixed(2)}</span>
                </div>

                {/* ── Implementation: milestones → tasks ── */}
                {comp.type === 'implementation' && comp.milestones && comp.milestones.map((ms, mi) => (
                  <div key={ms.id ?? mi} style={{ marginTop: mi === 0 ? 0 : '2px' }}>
                    <div style={{
                      background: '#f0f0f1',
                      padding: '6px 12px',
                      fontWeight: 600,
                      fontSize: '13px',
                      display: 'flex',
                      justifyContent: 'space-between',
                      borderBottom: '1px solid #ccd0d4',
                    }}>
                      <span>{ms.title}</span>
                      <span>${ms.sellValue.toFixed(2)}</span>
                    </div>
                    <table className="widefat fixed striped" style={{ border: '1px solid #ccd0d4', borderTop: 0 }}>
                      <thead>
                        <tr>
                          <th style={{ textAlign: 'left', padding: '6px 10px' }}>Task</th>
                          <th style={{ textAlign: 'right', padding: '6px 10px', width: '80px' }}>Hours</th>
                          <th style={{ textAlign: 'right', padding: '6px 10px', width: '90px' }}>Rate</th>
                          <th style={{ textAlign: 'right', padding: '6px 10px', width: '100px' }}>Value</th>
                        </tr>
                      </thead>
                      <tbody>
                        {ms.tasks.map((task, ti) => (
                          <tr key={task.id ?? ti}>
                            <td style={{ padding: '6px 10px' }}>{task.title}</td>
                            <td style={{ padding: '6px 10px', textAlign: 'right' }}>{task.durationHours}h</td>
                            <td style={{ padding: '6px 10px', textAlign: 'right' }}>${task.sellRate.toFixed(2)}</td>
                            <td style={{ padding: '6px 10px', textAlign: 'right' }}>${task.sellValue.toFixed(2)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ))}

                {/* ── Catalog: items ── */}
                {comp.type === 'catalog' && comp.items && (
                  <table className="widefat fixed striped" style={{ border: '1px solid #ccd0d4', borderTop: 0 }}>
                    <thead>
                      <tr>
                        <th style={{ textAlign: 'left', padding: '6px 10px' }}>Item</th>
                        <th style={{ textAlign: 'right', padding: '6px 10px', width: '60px' }}>Qty</th>
                        <th style={{ textAlign: 'right', padding: '6px 10px', width: '100px' }}>Unit Price</th>
                        <th style={{ textAlign: 'right', padding: '6px 10px', width: '100px' }}>Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      {comp.items.map((item, ii) => (
                        <tr key={ii}>
                          <td style={{ padding: '6px 10px' }}>{item.description}</td>
                          <td style={{ padding: '6px 10px', textAlign: 'right' }}>{item.quantity}</td>
                          <td style={{ padding: '6px 10px', textAlign: 'right' }}>${item.unitSellPrice.toFixed(2)}</td>
                          <td style={{ padding: '6px 10px', textAlign: 'right' }}>${item.sellValue.toFixed(2)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}

                {/* ── Once-off Service (simple): units ── */}
                {comp.type === 'once_off_service' && comp.topology === 'simple' && comp.units && (
                  <table className="widefat fixed striped" style={{ border: '1px solid #ccd0d4', borderTop: 0 }}>
                    <thead>
                      <tr>
                        <th style={{ textAlign: 'left', padding: '6px 10px' }}>Unit</th>
                        <th style={{ textAlign: 'right', padding: '6px 10px', width: '60px' }}>Qty</th>
                        <th style={{ textAlign: 'right', padding: '6px 10px', width: '100px' }}>Unit Price</th>
                        <th style={{ textAlign: 'right', padding: '6px 10px', width: '100px' }}>Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      {comp.units.map((unit, ui) => (
                        <tr key={unit.id ?? ui}>
                          <td style={{ padding: '6px 10px' }}>{unit.title}</td>
                          <td style={{ padding: '6px 10px', textAlign: 'right' }}>{unit.quantity}</td>
                          <td style={{ padding: '6px 10px', textAlign: 'right' }}>${unit.unitSellPrice.toFixed(2)}</td>
                          <td style={{ padding: '6px 10px', textAlign: 'right' }}>${unit.sellValue.toFixed(2)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}

                {/* ── Once-off Service (complex): phases → units ── */}
                {comp.type === 'once_off_service' && comp.topology === 'complex' && comp.phases && comp.phases.map((phase, pi) => (
                  <div key={phase.id ?? pi} style={{ marginTop: pi === 0 ? 0 : '2px' }}>
                    <div style={{
                      background: '#f0f0f1',
                      padding: '6px 12px',
                      fontWeight: 600,
                      fontSize: '13px',
                      display: 'flex',
                      justifyContent: 'space-between',
                      borderBottom: '1px solid #ccd0d4',
                    }}>
                      <span>{phase.name}</span>
                      <span>${phase.sellValue.toFixed(2)}</span>
                    </div>
                    <table className="widefat fixed striped" style={{ border: '1px solid #ccd0d4', borderTop: 0 }}>
                      <thead>
                        <tr>
                          <th style={{ textAlign: 'left', padding: '6px 10px' }}>Unit</th>
                          <th style={{ textAlign: 'right', padding: '6px 10px', width: '60px' }}>Qty</th>
                          <th style={{ textAlign: 'right', padding: '6px 10px', width: '100px' }}>Unit Price</th>
                          <th style={{ textAlign: 'right', padding: '6px 10px', width: '100px' }}>Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        {phase.units.map((unit, ui) => (
                          <tr key={unit.id ?? ui}>
                            <td style={{ padding: '6px 10px' }}>{unit.title}</td>
                            <td style={{ padding: '6px 10px', textAlign: 'right' }}>{unit.quantity}</td>
                            <td style={{ padding: '6px 10px', textAlign: 'right' }}>${unit.unitSellPrice.toFixed(2)}</td>
                            <td style={{ padding: '6px 10px', textAlign: 'right' }}>${unit.sellValue.toFixed(2)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ))}

                {/* ── Recurring Service: summary card ── */}
                {comp.type === 'recurring' && (
                  <div style={{
                    border: '1px solid #ccd0d4',
                    borderTop: 0,
                    padding: '12px',
                    background: '#fff',
                    display: 'grid',
                    gridTemplateColumns: 'repeat(3, 1fr)',
                    gap: '12px',
                    fontSize: '13px',
                  }}>
                    <div><strong>Service:</strong> {comp.serviceName}</div>
                    <div><strong>Cadence:</strong> {comp.cadence}</div>
                    <div><strong>Term:</strong> {comp.termMonths} months</div>
                    <div><strong>Renewal:</strong> {comp.renewalModel}</div>
                    <div><strong>Price/period:</strong> ${comp.sellPricePerPeriod?.toFixed(2)}</div>
                    <div><strong>Cost/period:</strong> ${comp.internalCostPerPeriod?.toFixed(2)}</div>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}

      <h3>Quote Sections</h3>
      {sectionsForRendering.length > 0 && (
        <div>
          {sectionsForRendering.map((section, sectionIndex) => {
            const blocksInSection = blocksForRendering.filter(
              (block) => block.sectionId === section.id
            );
            const hasNonTextBlocks =
              blocksInSection.length > 0 &&
              blocksInSection.some((block) => block.type !== 'TextBlock');
            const sectionTotal = sectionTotals[section.id] ?? 0;
            const isTextSection =
              blocksInSection.length > 0 &&
              blocksInSection.every((block) => block.type === 'TextBlock');
            return (
              <div key={section.id} style={{ marginBottom: '20px' }}>
                <div
                  style={{
                    background: '#000',
                    color: '#fff',
                    padding: '8px 12px',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    position: 'sticky',
                    top: '80px',
                    zIndex: 3,
                  }}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    {editingSectionId === section.id ? (
                      <input
                        type="text"
                        value={sectionDraftNames[section.id] ?? section.name}
                        placeholder={isTextSection ? 'Section heading (optional)' : undefined}
                        onChange={(e) =>
                          handleSectionNameChange(section.id, e.target.value)
                        }
                        onBlur={() => handleSectionNameBlur(section.id)}
                        autoFocus
                        style={{
                          background: '#111',
                          color: '#fff',
                          border: '1px solid #444',
                          padding: '2px 4px',
                        }}
                      />
                    ) : (
                      <span
                        style={{
                          cursor: 'pointer',
                          opacity: isTextSection && !section.name ? 0.6 : 1,
                          fontStyle:
                            isTextSection && !section.name ? 'italic' : 'normal',
                        }}
                        onClick={() =>
                          handleSectionNameClick(section.id, section.name)
                        }
                      >
                        {section.name || (isTextSection ? 'Click to add heading' : '')}
                      </span>
                    )}
                    <KebabMenu
                      light
                      hasNotification={activeSubjectKeys.has(`quote_section:${section.id}`)}
                      items={[
                        { type: 'action', label: 'Rename', onClick: () => handleSectionNameClick(section.id, section.name) },
                        { type: 'action', label: 'Clone section', onClick: () => handleCloneSection(section.id) },
                        { type: 'action', label: 'Discuss', onClick: () => openConversation({ contextType: 'quote', contextId: quote.id.toString(), contextVersion: quote.version.toString(), subject: `Section: ${section.name || 'Untitled'}`, subjectKey: `quote_section:${section.id}` }), hasNotification: activeSubjectKeys.has(`quote_section:${section.id}`) },
                        { type: 'divider' },
                        ...(sectionIndex > 0
                          ? [{ type: 'action' as const, label: 'Move Up', onClick: () => handleMoveSection(section.id, sectionsForRendering[sectionIndex - 1].id) }]
                          : []),
                        ...(sectionIndex < sectionsForRendering.length - 1
                          ? [{ type: 'action' as const, label: 'Move Down', onClick: () => handleMoveSection(section.id, sectionsForRendering[sectionIndex + 1].id) }]
                          : []),
                        { type: 'divider' },
                        { type: 'toggle', label: 'Show total value', checked: section.showTotalValue, onChange: () => handleSectionToggle(section.id, 'showTotalValue') },
                        { type: 'toggle', label: 'Show item count', checked: section.showItemCount, onChange: () => handleSectionToggle(section.id, 'showItemCount') },
                        { type: 'toggle', label: 'Show total hours', checked: section.showTotalHours, onChange: () => handleSectionToggle(section.id, 'showTotalHours') },
                        { type: 'divider' },
                        { type: 'action', label: 'Delete section', onClick: () => handleDeleteSection(section.id, hasNonTextBlocks), danger: true, disabled: hasNonTextBlocks, disabledReason: 'Cannot delete a section that contains non-text blocks.' },
                      ]}
                    />
                  </div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '12px', fontSize: '11px', opacity: 0.8 }}>
                    {section.showItemCount && blocksInSection.length > 0 && (
                      <span>{blocksInSection.length} item{blocksInSection.length !== 1 ? 's' : ''}</span>
                    )}
                    {section.showTotalValue && (
                      <span>${sectionTotal.toFixed(2)}</span>
                    )}
                    {quoteTotal > 0 && sectionTotal > 0 && (
                      <span>{((sectionTotal / quoteTotal) * 100).toFixed(1)}% of quote</span>
                    )}
                    {sectionTotal > 0 && adjustedCost > 0 && (() => {
                      const sectionCostRatio = sectionTotal / quoteTotal;
                      const sectionCost = adjustedCost * sectionCostRatio;
                      const sectionMargin = sectionTotal - sectionCost;
                      const marginPct = (sectionMargin / sectionTotal) * 100;
                      return <span style={{ color: marginPct >= 0 ? '#a0e6a0' : '#ffaaaa' }}>{marginPct.toFixed(0)}% margin</span>;
                    })()}
                  </div>
                </div>
                {blocksInSection.length > 0 ? (
                  isTextSection ? (
                    <div
                      style={{
                        marginTop: '10px',
                        border: '1px solid #ccd0d4',
                        padding: '10px',
                        background: '#fff',
                      }}
                    >
                      {blocksInSection.map((block) => {
                        const payload = block.payload || {};
                        const text =
                          typeof payload.text === 'string' ? payload.text : '';
                        const isExpanded = expandedBlockId === block.id;
                        const draft = blockDrafts[block.id] || {};

                        return (
                          <div
                            key={block.id}
                            style={{
                              marginBottom: '12px',
                              borderBottom: '1px solid #eee',
                              paddingBottom: '8px',
                            }}
                          >
                            <div
                              style={{ whiteSpace: 'pre-wrap', cursor: isExpanded ? 'default' : 'pointer' }}
                              onClick={() => { if (!isExpanded) openBlockEditor(block); }}
                            >
                              {text || 'Empty text block'}
                            </div>
                            <div style={{ marginTop: '6px', textAlign: 'right' }}>
                              <KebabMenu
                                hasNotification={activeSubjectKeys.has(`quote_line:${block.id}`)}
                                items={[
                                  { type: 'action', label: isExpanded ? 'Close' : 'Edit', onClick: () => isExpanded ? cancelBlockEdit(block.id) : openBlockEditor(block) },
                                  { type: 'action', label: 'Discuss', onClick: () => openConversation({ contextType: 'quote', contextId: quote.id.toString(), contextVersion: quote.version.toString(), subject: `Text Block`, subjectKey: `quote_line:${block.id}` }), hasNotification: activeSubjectKeys.has(`quote_line:${block.id}`) },
                                  { type: 'divider' },
                                  { type: 'action', label: 'Delete', onClick: () => handleDeleteBlock(block.id), danger: true, disabled: isExpanded, disabledReason: 'Cannot delete while editing' },
                                ]}
                              />
                            </div>
                            {isExpanded && (
                              <div
                                style={{
                                  marginTop: '10px',
                                  padding: '10px',
                                  background: '#f8f9fa',
                                  border: '2px solid #46b450',
                                }}
                              >
                                <div style={{ marginBottom: '10px' }}>
                                  <div>Text</div>
                                  <MarkdownTextarea
                                    value={draft.text || ''}
                                    onChange={(val) =>
                                      updateBlockDraft(block.id, 'text', val)
                                    }
                                  />
                                </div>
                                <div
                                  style={{
                                    display: 'flex',
                                    justifyContent: 'flex-end',
                                    gap: '8px',
                                  }}
                                >
                                  <button
                                    className="button button-primary"
                                    onClick={() => saveBlock(block)}
                                    disabled={savingBlockId === block.id}
                                  >
                                    {savingBlockId === block.id
                                      ? 'Saving...'
                                      : 'Save'}
                                  </button>
                                  <button
                                    className="button"
                                    onClick={() => cancelBlockEdit(block.id)}
                                    disabled={savingBlockId === block.id}
                                  >
                                    Cancel
                                  </button>
                                </div>
                              </div>
                            )}
                          </div>
                        );
                      })}
                    </div>
                  ) : (
                  <table
                    className="widefat fixed striped"
                    style={{ marginTop: '10px', border: '1px solid #ccd0d4' }}
                  >
                    <thead>
                      <tr>
                        <th style={{ textAlign: 'left', padding: '10px', width: '23%' }}>Description</th>
                        <th style={{ textAlign: 'left', padding: '10px', width: '10%' }}>Role</th>
                        <th style={{ textAlign: 'left', padding: '10px', width: '11%' }}>Owner/Team</th>
                        <th style={{ textAlign: 'right', padding: '10px', width: '6%' }}>Qty</th>
                        <th style={{ textAlign: 'center', padding: '10px', width: '7%' }}>Unit</th>
                        <th style={{ textAlign: 'right', padding: '10px', width: '10%' }}>Unit Price</th>
                        <th style={{ textAlign: 'right', padding: '10px', width: '10%' }}>Total</th>
                        <th style={{ textAlign: 'right', padding: '10px', width: '13%' }}>Margin</th>
                        <th style={{ textAlign: 'right', padding: '10px', width: '10%' }}>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      {blocksInSection.map((block, blockIndex) => {
                        const isExpanded = expandedBlockId === block.id;
                        const draft = blockDrafts[block.id] || {};
                        const isFirst = blockIndex === 0;
                        const isLast = blockIndex === blocksInSection.length - 1;
                        const blockCallbacks: BlockRowCallbacks = {
                          onEdit: (b) => isExpanded ? cancelBlockEdit(b.id) : openBlockEditor(b),
                          onDelete: (id) => handleDeleteBlock(id),
                          onDiscuss: (b) => {
                            const desc = (b.payload && typeof b.payload.description === 'string') ? b.payload.description : b.type;
                            openConversation({
                              contextType: 'quote',
                              contextId: quote.id.toString(),
                              contextVersion: quote.version.toString(),
                              subject: `Block: ${desc}`,
                              subjectKey: `quote_line:${b.id}`,
                            });
                          },
                          ...(blockSnapshots[block.id] ? { onRevert: (id: number) => revertBlock(id) } : {}),
                          ...(!isFirst ? { onMoveUp: () => handleMoveBlock(block.id, blocksInSection[blockIndex - 1].id) } : {}),
                          ...(!isLast ? { onMoveDown: () => handleMoveBlock(block.id, blocksInSection[blockIndex + 1].id) } : {}),
                        };

                        // Service block: inline editor replaces the row
                        if (block.type === 'OnceOffSimpleServiceBlock' && isExpanded) {
                          return (
                            <ServiceBlockEditor
                              key={block.id}
                              draft={{
                                description: draft.description ?? '',
                                catalogItemId: draft.catalogItemId ?? null,
                                roleId: draft.roleId ?? null,
                                ownerType: draft.ownerType ?? '',
                                ownerId: draft.ownerId ?? null,
                                teamId: draft.teamId ?? null,
                                owner: draft.owner ?? '',
                                team: draft.team ?? '',
                                quantity: draft.quantity ?? 1,
                                unit: draft.unit ?? 'hours',
                                sellValue: draft.sellValue ?? 0,
                                unitCost: draft.unitCost ?? null,
                                totalCost: draft.totalCost ?? null,
                                price_override: draft.price_override ?? false,
                              }}
                              onDraftChange={(field, value) => updateBlockDraft(block.id, field, value)}
                              onSave={() => saveBlock(block)}
                              onCancel={() => cancelBlockEdit(block.id)}
                              saving={savingBlockId === block.id}
                              serverError={serverErrors[block.id] ?? null}
                              catalogItems={catalogItems}
                              roles={roles}
                              employees={employees}
                              teams={teams}
                              ownerOptions={draft.roleId ? ownerOptionsCache[draft.roleId] ?? null : null}
                              onCatalogItemCreated={(item) => setCatalogItems((prev) => [...prev, item])}
                              onRoleChange={(roleId) => {
                                if (roleId) fetchOwnerOptions(roleId);
                                // Prepopulate price from rate card
                                if (roleId && !draft.price_override) {
                                  const rc = rateCards.find((r) => r.role_id === roleId);
                                  if (rc) {
                                    updateBlockDraft(block.id, 'sellValue', rc.sell_rate);
                                    updateBlockDraft(block.id, 'price_override', false);
                                  }
                                }
                              }}
                            />
                          );
                        }

                        // Project block: always expanded (no collapse)
                        if (block.type === 'OnceOffProjectBlock') {
                          const payload = block.payload || {};
                          // Draft is auto-initialized by useEffect; fallback to payload
                          const projectDraft = blockDrafts[block.id] || {
                            description: payload.description ?? '',
                            phases: Array.isArray(payload.phases) ? payload.phases : [],
                          };
                          const projectDirty = isProjectBlockDirty(blockDrafts[block.id], payload);
                          return (
                            <React.Fragment key={block.id}>
                              <BlockRow
                                block={block}
                                roles={roles}
                                callbacks={blockCallbacks}
                                hasNotification={activeSubjectKeys.has(`quote_line:${block.id}`)}
                                isEditing={projectDirty}
                                projectSummary={computeProjectSummary(payload)}
                                editableDescription={{
                                  value: projectDraft.description ?? '',
                                  onChange: (val: string) => updateBlockDraft(block.id, 'description', val),
                                }}
                              />
                              <tr>
                                <td colSpan={9} style={{ padding: 0, background: '#f8f9fa', borderLeft: projectDirty ? '3px solid #46b450' : '3px solid transparent' }}>
                                      <ProjectBlockEditor
                                        draft={{
                                          description: projectDraft.description ?? '',
                                          phases: (Array.isArray(projectDraft.phases) ? projectDraft.phases : []).map((phase: any) => ({
                                            id: phase.id ?? null,
                                            name: phase.name ?? '',
                                            phaseTotalCost: phase.phaseTotalCost ?? null,
                                            marginAmount: phase.marginAmount ?? null,
                                            marginPercentage: phase.marginPercentage ?? null,
                                            hasMarginData: phase.hasMarginData ?? false,
                                            units: (Array.isArray(phase.units) ? phase.units : []).map((unit: any) => ({
                                              id: unit.id ?? null,
                                              title: unit.description ?? '',
                                              description: unit.description ?? '',
                                              catalogItemId: unit.catalogItemId ?? null,
                                              roleId: unit.roleId ?? null,
                                              ownerType: unit.ownerType ?? '',
                                              ownerId: unit.ownerId ?? null,
                                              teamId: unit.teamId ?? null,
                                              owner: unit.owner ?? '',
                                              team: unit.team ?? '',
                                              quantity: unit.quantity ?? 1,
                                              unit: unit.unit ?? 'hours',
                                              unitPrice: unit.unitPrice ?? 0,
                                              totalValue: unit.totalValue ?? 0,
                                              unitCost: unit.unitCost ?? null,
                                              totalCost: unit.totalCost ?? null,
                                              marginAmount: unit.marginAmount ?? null,
                                              marginPercentage: unit.marginPercentage ?? null,
                                              hasMarginData: unit.hasMarginData ?? false,
                                              price_override: unit.price_override ?? false,
                                            })),
                                          })),
                                        }}
                                        onDraftChange={(newDraft) => {
                                          setBlockDrafts((prev) => ({
                                            ...prev,
                                            [block.id]: {
                                              ...(prev[block.id] || {}),
                                              description: newDraft.description,
                                              phases: newDraft.phases.map((phase) => ({
                                                ...phase,
                                                units: phase.units.map((u) => ({
                                                  ...u,
                                                  unitPrice: u.unitPrice,
                                                })),
                                                phaseTotalValue: phase.units.reduce(
                                                  (sum, u) => sum + (Number(u.totalValue) || 0),
                                                  0
                                                ),
                                              })),
                                            },
                                          }));
                                        }}
                                        roles={roles}
                                        employees={employees}
                                        teams={teams}
                                        catalogItems={catalogItems}
                                        ownerOptionsCache={ownerOptionsCache}
                                        rateCards={rateCards}
                                        onRoleChange={(roleId) => { if (roleId) fetchOwnerOptions(roleId); }}
                                        onDiscussPhase={(phaseName, phaseId) => {
                                          openConversation({
                                            contextType: 'quote',
                                            contextId: quote.id.toString(),
                                            contextVersion: quote.version.toString(),
                                            subject: `Phase: ${phaseName}`,
                                            subjectKey: `quote_phase:${block.id}:${phaseId ?? 'new'}`,
                                          });
                                        }}
                                        onDiscussUnit={(unitDesc, unitId) => {
                                          openConversation({
                                            contextType: 'quote',
                                            contextId: quote.id.toString(),
                                            contextVersion: quote.version.toString(),
                                            subject: `Unit: ${unitDesc}`,
                                            subjectKey: `quote_unit:${block.id}:${unitId ?? 'new'}`,
                                          });
                                        }}
                                      />
                                    </td>
                                  </tr>
                                  {projectDirty && (
                                  <tr>
                                    <td colSpan={9} style={{ padding: '10px', background: '#f8f9fa', borderLeft: '3px solid #46b450', textAlign: 'right' }}>
                                      <button
                                        className="button button-primary"
                                        onClick={() => saveBlock(block)}
                                        disabled={savingBlockId === block.id}
                                      >
                                        {savingBlockId === block.id ? 'Saving...' : 'Save'}
                                      </button>
                                      <button
                                        className="button"
                                        style={{ marginLeft: '8px' }}
                                        onClick={() => {
                                          // Reset draft from current server payload
                                          const p = block.payload || {};
                                          setBlockDrafts((prev) => ({
                                            ...prev,
                                            [block.id]: {
                                              description: p.description ?? '',
                                              quantity: p.quantity ?? 1,
                                              sellValue: p.sellValue ?? p.totalValue ?? 0,
                                              owner: p.owner ?? '',
                                              team: p.team ?? '',
                                              ownerType: p.ownerType ?? '',
                                              ownerId: p.ownerId ?? null,
                                              teamId: p.teamId ?? null,
                                              totalValue: p.totalValue ?? p.sellValue ?? 0,
                                              type: block.type,
                                              phases: Array.isArray(p.phases) ? p.phases : [],
                                            },
                                          }));
                                          setServerErrors((prev) => {
                                            const next = { ...prev };
                                            delete next[block.id];
                                            return next;
                                          });
                                        }}
                                        disabled={savingBlockId === block.id}
                                      >
                                        Cancel
                                      </button>
                                    </td>
                                  </tr>
                                  )}
                            </React.Fragment>
                          );
                        }

                        // All other block types: BlockRow + old-style editor when expanded
                        return (
                          <React.Fragment key={block.id}>
                            <BlockRow
                              block={block}
                              roles={roles}
                              callbacks={blockCallbacks}
                              hasNotification={activeSubjectKeys.has(`quote_line:${block.id}`)}
                              isEditing={isExpanded}
                            />
                            {isExpanded && (
                              <tr>
                                <td colSpan={9} style={{ padding: '10px', background: '#f8f9fa', borderLeft: '3px solid #46b450' }}>
                                  {block.type === 'HardwareBlock' && (
                                    <div
                                      style={{
                                        display: 'grid',
                                        gridTemplateColumns: '2fr 1fr 1fr',
                                        gap: '10px',
                                        marginBottom: '10px',
                                      }}
                                    >
                                      <div>
                                        <div>Catalog Product</div>
                                        <select
                                          value={draft.catalogItemId ?? ''}
                                          onChange={(e) => {
                                            const value = e.target.value;
                                            const id = value ? Number(value) : null;
                                            updateBlockDraft(block.id, 'catalogItemId', id);
                                            if (id !== null) {
                                              const item = catalogItems.find(
                                                (c) => c.id === id && c.type === 'product'
                                              );
                                              if (item) {
                                                updateBlockDraft(block.id, 'description', item.name);
                                                updateBlockDraft(block.id, 'unitPrice', item.unit_price);
                                                const qty = Number(draft.quantity ?? 1);
                                                if (Number.isFinite(qty)) {
                                                  updateBlockDraft(block.id, 'totalValue', qty * item.unit_price);
                                                }
                                              }
                                            }
                                          }}
                                          style={{ width: '100%' }}
                                        >
                                          <option value="">Select from product catalog</option>
                                          {catalogItems
                                            .filter((item) => item.type === 'product')
                                            .map((item) => (
                                              <option key={item.id} value={item.id}>
                                                {item.name}
                                              </option>
                                            ))}
                                        </select>
                                      </div>
                                      <div>
                                        <div>Quantity</div>
                                        <input
                                          type="number"
                                          min={0}
                                          value={draft.quantity ?? 1}
                                          onChange={(e) => {
                                            const value = e.target.value;
                                            updateBlockDraft(block.id, 'quantity', value);
                                            const qty = Number(value);
                                            const price = Number(draft.unitPrice ?? 0);
                                            if (Number.isFinite(qty) && Number.isFinite(price)) {
                                              updateBlockDraft(block.id, 'totalValue', qty * price);
                                            }
                                          }}
                                          style={{ width: '100%' }}
                                        />
                                      </div>
                                      <div>
                                        <div>Unit Price</div>
                                        <input
                                          type="number"
                                          min={0}
                                          value={draft.unitPrice ?? 0}
                                          onChange={(e) => {
                                            const value = e.target.value;
                                            updateBlockDraft(block.id, 'unitPrice', value);
                                            const price = Number(value);
                                            const qty = Number(draft.quantity ?? 1);
                                            if (Number.isFinite(qty) && Number.isFinite(price)) {
                                              updateBlockDraft(block.id, 'totalValue', qty * price);
                                            }
                                          }}
                                          style={{ width: '100%' }}
                                        />
                                      </div>
                                    </div>
                                  )}

                                  {block.type === 'PriceAdjustmentBlock' && (
                                    <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '10px', marginBottom: '10px' }}>
                                      <div>
                                        <div>Description</div>
                                        <input
                                          type="text"
                                          value={draft.description || ''}
                                          onChange={(e) =>
                                            updateBlockDraft(block.id, 'description', e.target.value)
                                          }
                                          style={{ width: '100%' }}
                                        />
                                      </div>
                                      <div>
                                        <div>Amount</div>
                                        <input
                                          type="number"
                                          value={draft.amount ?? 0}
                                          onChange={(e) =>
                                            updateBlockDraft(block.id, 'amount', e.target.value)
                                          }
                                          style={{ width: '100%' }}
                                        />
                                      </div>
                                    </div>
                                  )}

                                  {block.type === 'TextBlock' && (
                                    <div style={{ marginBottom: '10px' }}>
                                      <div>Text</div>
                                      <MarkdownTextarea
                                        value={draft.text || ''}
                                        onChange={(val) =>
                                          updateBlockDraft(block.id, 'text', val)
                                        }
                                      />
                                    </div>
                                  )}

                                  <div
                                    style={{
                                      marginTop: '10px',
                                      display: 'flex',
                                      justifyContent: 'flex-end',
                                      gap: '8px',
                                    }}
                                  >
                                    <button
                                      className="button button-primary"
                                      onClick={() => saveBlock(block)}
                                      disabled={savingBlockId === block.id}
                                    >
                                      {savingBlockId === block.id ? 'Saving...' : 'Save'}
                                    </button>
                                    <button
                                      className="button"
                                      onClick={() => cancelBlockEdit(block.id)}
                                      disabled={savingBlockId === block.id}
                                    >
                                      Cancel
                                    </button>
                                  </div>
                                </td>
                              </tr>
                            )}
                          </React.Fragment>
                        );
                      })}
                    </tbody>
                  </table>
                  )
                ) : (
                  <EmptyState message="No blocks in this section yet." />
                )}
                <div style={{ marginTop: '8px' }}>
                  <button
                    className="button button-secondary"
                    onClick={() => handleOpenBlockTypeSelection(section.id)}
                  >
                    + Add Block
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}
      {sectionsForRendering.length === 0 && (
        <EmptyState message="No sections defined yet." />
      )}

      {blocksForRendering.some((block) => block.sectionId === null) && (
        <div style={{ marginTop: '30px' }}>
          <h3>General</h3>
          <table
            className="widefat fixed striped"
            style={{ marginTop: '10px', border: '1px solid #ccd0d4' }}
          >
            <thead>
              <tr>
                <th style={{ textAlign: 'left', padding: '10px' }}>Type</th>
                <th style={{ textAlign: 'left', padding: '10px' }}>Details</th>
                <th style={{ textAlign: 'right', padding: '10px' }}>Value</th>
                <th style={{ textAlign: 'right', padding: '10px' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {blocksForRendering
                .filter((block) => block.sectionId === null)
                .map((block) => {
                  const payload = block.payload || {};

                  let description = '';
                  if (block.type === 'TextBlock') {
                    const text =
                      typeof payload.text === 'string' ? payload.text : '';
                    description =
                      text.length > 80 ? `${text.slice(0, 80)}…` : text;
                  } else if (block.type === 'HardwareBlock') {
                    const baseName =
                      typeof payload.description === 'string'
                        ? payload.description
                        : '';
                    const quantity =
                      typeof payload.quantity === 'number'
                        ? payload.quantity
                        : 1;
                    description = baseName
                      ? `${baseName} (Qty ${quantity})`
                      : `Qty ${quantity}`;
                  } else if (
                    payload &&
                    typeof payload.description === 'string'
                  ) {
                    description = payload.description;
                  }

                  let value: number | null = null;
                  if (block.type === 'PriceAdjustmentBlock') {
                    const rawAmount =
                      typeof payload.amount === 'number'
                        ? payload.amount
                        : typeof payload.amount === 'string'
                        ? parseFloat(payload.amount)
                        : 0;
                    value = Number.isFinite(rawAmount) ? rawAmount : 0;
                  } else if (block.type !== 'TextBlock') {
                    if (typeof payload.totalValue === 'number') {
                      value = payload.totalValue;
                    } else if (typeof payload.sellValue === 'number') {
                      value = payload.sellValue;
                    } else {
                      value = null;
                    }
                  }

                  const isExpanded = expandedBlockId === block.id;
                  const draft = blockDrafts[block.id] || {};

                  return (
                    <React.Fragment key={block.id}>
                      <tr>
                        <td style={{ padding: '10px' }}>{block.type}</td>
                        <td style={{ padding: '10px' }}>
                          {description || 'Block placeholder'}
                        </td>
                        <td style={{ padding: '10px', textAlign: 'right' }}>
                          {value !== null ? `$${value.toFixed(2)}` : '-'}
                        </td>
                        <td style={{ padding: '10px', textAlign: 'right' }}>
                          <button
                            className="button button-small"
                            onClick={() =>
                              isExpanded ? cancelBlockEdit(block.id) : openBlockEditor(block)
                            }
                          >
                            {isExpanded ? 'Close' : 'Edit'}
                          </button>
                          <button
                            className="button button-small"
                            style={{ marginLeft: '8px' }}
                            disabled={isExpanded}
                            title={isExpanded ? 'Cannot delete while editing' : undefined}
                            onClick={() => handleDeleteBlock(block.id)}
                          >
                            Delete
                          </button>
                        </td>
                      </tr>
                      {isExpanded && (
                        <tr>
                          <td colSpan={4} style={{ padding: '10px', background: '#f8f9fa' }}>
                            {block.type === 'OnceOffSimpleServiceBlock' && (
                              <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 1fr', gap: '10px', marginBottom: '10px' }}>
                                <div>
                                  <div>Description</div>
                                  <input
                                    type="text"
                                    value={draft.description || ''}
                                    onChange={(e) =>
                                      updateBlockDraft(block.id, 'description', e.target.value)
                                    }
                                    style={{ width: '100%' }}
                                  />
                                </div>
                                <div>
                                  <div>Quantity</div>
                                  <input
                                    type="number"
                                    min={0}
                                    value={draft.quantity ?? 1}
                                    onChange={(e) =>
                                      updateBlockDraft(block.id, 'quantity', e.target.value)
                                    }
                                    style={{ width: '100%' }}
                                  />
                                </div>
                                <div>
                                  <div>Sell Value</div>
                                  <input
                                    type="number"
                                    min={0}
                                    value={draft.sellValue ?? 0}
                                    onChange={(e) =>
                                      updateBlockDraft(block.id, 'sellValue', e.target.value)
                                    }
                                    style={{ width: '100%' }}
                                  />
                                </div>
                                <div>
                                  <div>Owner/Team</div>
                                  <input
                                    type="text"
                                    value={draft.owner || draft.team || ''}
                                    onChange={(e) =>
                                      updateBlockDraft(block.id, 'owner', e.target.value)
                                    }
                                    style={{ width: '100%' }}
                                  />
                                </div>
                              </div>
                            )}

                            {block.type === 'OnceOffProjectBlock' && (
                              <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '10px', marginBottom: '10px' }}>
                                <div>
                                  <div>Description</div>
                                  <input
                                    type="text"
                                    value={draft.description || ''}
                                    onChange={(e) =>
                                      updateBlockDraft(block.id, 'description', e.target.value)
                                    }
                                    style={{ width: '100%' }}
                                  />
                                </div>
                                <div>
                                  <div>Total Value</div>
                                  <input
                                    type="number"
                                    min={0}
                                    value={draft.totalValue ?? 0}
                                    onChange={(e) =>
                                      updateBlockDraft(block.id, 'totalValue', e.target.value)
                                    }
                                    style={{ width: '100%' }}
                                  />
                                </div>
                              </div>
                            )}

                            {block.type === 'PriceAdjustmentBlock' && (
                              <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '10px', marginBottom: '10px' }}>
                                <div>
                                  <div>Description</div>
                                  <input
                                    type="text"
                                    value={draft.description || ''}
                                    onChange={(e) =>
                                      updateBlockDraft(
                                        block.id,
                                        'description',
                                        e.target.value
                                      )
                                    }
                                    style={{ width: '100%' }}
                                  />
                                </div>
                                <div>
                                  <div>Amount</div>
                                  <input
                                    type="number"
                                    value={draft.amount ?? 0}
                                    onChange={(e) =>
                                      updateBlockDraft(
                                        block.id,
                                        'amount',
                                        e.target.value
                                      )
                                    }
                                    style={{ width: '100%' }}
                                  />
                                </div>
                              </div>
                            )}

                            {block.type === 'TextBlock' && (
                              <div style={{ marginBottom: '10px' }}>
                                <div>Text</div>
                                <MarkdownTextarea
                                  value={draft.text || ''}
                                  onChange={(val) =>
                                    updateBlockDraft(
                                      block.id,
                                      'text',
                                      val
                                    )
                                  }
                                />
                              </div>
                            )}

                            {block.type === 'HardwareBlock' && (
                              <div
                                style={{
                                  display: 'grid',
                                  gridTemplateColumns: '2fr 1fr 1fr',
                                  gap: '10px',
                                  marginBottom: '10px',
                                }}
                              >
                                <div>
                                  <div>Catalog Item</div>
                                  <select
                                    value={draft.catalogItemId ?? ''}
                                    onChange={(e) => {
                                      const value = e.target.value;
                                      const id = value ? Number(value) : null;
                                      updateBlockDraft(
                                        block.id,
                                        'catalogItemId',
                                        id
                                      );
                                      if (id !== null) {
                                        const item = catalogItems.find(
                                          (c) => c.id === id
                                        );
                                        if (item) {
                                          updateBlockDraft(
                                            block.id,
                                            'description',
                                            item.name
                                          );
                                          updateBlockDraft(
                                            block.id,
                                            'unitPrice',
                                            item.unit_price
                                          );
                                          const qty = Number(
                                            draft.quantity ?? 1
                                          );
                                          if (Number.isFinite(qty)) {
                                            updateBlockDraft(
                                              block.id,
                                              'totalValue',
                                              qty * item.unit_price
                                            );
                                          }
                                        }
                                      }
                                    }}
                                    style={{ width: '100%' }}
                                  >
                                    <option value="">
                                      Select from catalog
                                    </option>
                                    {catalogItems.map((item) => (
                                      <option key={item.id} value={item.id}>
                                        {item.name}
                                      </option>
                                    ))}
                                  </select>
                                </div>
                                <div>
                                  <div>Quantity</div>
                                  <input
                                    type="number"
                                    min={0}
                                    value={draft.quantity ?? 1}
                                    onChange={(e) => {
                                      const value = e.target.value;
                                      updateBlockDraft(
                                        block.id,
                                        'quantity',
                                        value
                                      );
                                      const qty = Number(value);
                                      const price = Number(
                                        draft.unitPrice ?? 0
                                      );
                                      if (
                                        Number.isFinite(qty) &&
                                        Number.isFinite(price)
                                      ) {
                                        updateBlockDraft(
                                          block.id,
                                          'totalValue',
                                          qty * price
                                        );
                                      }
                                    }}
                                    style={{ width: '100%' }}
                                  />
                                </div>
                                <div>
                                  <div>Unit Price</div>
                                  <input
                                    type="number"
                                    min={0}
                                    value={draft.unitPrice ?? 0}
                                    onChange={(e) => {
                                      const value = e.target.value;
                                      updateBlockDraft(
                                        block.id,
                                        'unitPrice',
                                        value
                                      );
                                      const price = Number(value);
                                      const qty = Number(draft.quantity ?? 1);
                                      if (
                                        Number.isFinite(qty) &&
                                        Number.isFinite(price)
                                      ) {
                                        updateBlockDraft(
                                          block.id,
                                          'totalValue',
                                          qty * price
                                        );
                                      }
                                    }}
                                    style={{ width: '100%' }}
                                  />
                                </div>
                              </div>
                            )}

                            <div
                              style={{
                                marginTop: '10px',
                                display: 'flex',
                                justifyContent: 'flex-end',
                                gap: '8px',
                              }}
                            >
                              <button
                                className="button button-primary"
                                onClick={() => saveBlock(block)}
                                disabled={savingBlockId === block.id}
                              >
                                {savingBlockId === block.id ? 'Saving...' : 'Save'}
                              </button>
                              <button
                                className="button"
                                onClick={() => cancelBlockEdit(block.id)}
                                disabled={savingBlockId === block.id}
                              >
                                Cancel
                              </button>
                            </div>
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  );
                })}
            </tbody>
          </table>
        </div>
      )}

      {(showAdjustmentForm || (quote.costAdjustments && quote.costAdjustments.length > 0)) && (
        <div style={{ marginTop: '30px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <h3>Cost Adjustments</h3>
              {!showAdjustmentForm && (
                  <button className="button" onClick={() => setShowAdjustmentForm(true)}>Add Adjustment</button>
              )}
          </div>
          
          {showAdjustmentForm && (
              <div className="card" style={{ padding: '20px', marginTop: '15px', background: '#fff', border: '1px solid #ccd0d4', maxWidth: '600px' }}>
                  <h4>Add Cost Adjustment</h4>
                  <AddCostAdjustmentForm 
                      quoteId={quoteId}
                      onSuccess={handleAdjustmentAdded}
                      onCancel={() => setShowAdjustmentForm(false)}
                  />
              </div>
          )}

          {quote.costAdjustments && quote.costAdjustments.length > 0 ? (
            <table className="widefat fixed striped" style={{ marginTop: '10px', border: '1px solid #ccd0d4' }}>
              <thead>
                  <tr>
                      <th style={{ textAlign: 'left', padding: '10px' }}>Description</th>
                      <th style={{ textAlign: 'left', padding: '10px' }}>Amount</th>
                      <th style={{ textAlign: 'left', padding: '10px' }}>Reason</th>
                      <th style={{ textAlign: 'left', padding: '10px' }}>Approved By</th>
                      <th style={{ textAlign: 'left', padding: '10px' }}>Date</th>
                      <th style={{ textAlign: 'right', padding: '10px' }}>Actions</th>
                  </tr>
              </thead>
              <tbody>
                  {quote.costAdjustments.map(adj => (
                      <tr key={adj.id}>
                          <td style={{ padding: '10px' }}>{adj.description}</td>
                          <td style={{ padding: '10px' }}>${adj.amount.toFixed(2)}</td>
                          <td style={{ padding: '10px' }}>{adj.reason}</td>
                          <td style={{ padding: '10px' }}>{adj.approvedBy}</td>
                          <td style={{ padding: '10px' }}>{new Date(adj.appliedAt).toLocaleDateString()}</td>
                          <td style={{ padding: '10px', textAlign: 'right' }}>
                              <button 
                                  className="button button-link-delete" 
                                  onClick={() => handleRemoveAdjustment(adj.id)}
                                  style={{ color: '#a00' }}
                              >
                                  Remove
                              </button>
                          </td>
                      </tr>
                  ))}
              </tbody>
            </table>
          ) : (
              !showAdjustmentForm && <p style={{ fontStyle: 'italic', color: '#666' }}>No cost adjustments recorded.</p>
          )}
        </div>
      )}

      {(quote.paymentSchedule && quote.paymentSchedule.length > 0) && (
        <div className="card" style={{ padding: '20px', marginTop: '30px', background: '#fff', border: '1px solid #ccd0d4', maxWidth: '900px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <h3>Payment Schedule</h3>
            {paymentScheduleDelta !== 0 && (
              <div
                style={{
                  color: '#d63638',
                  fontWeight: 600,
                  fontSize: '13px',
                  textTransform: 'uppercase',
                }}
              >
                ${Math.abs(paymentScheduleDelta).toFixed(2)} VALUE{' '}
                {paymentScheduleDelta < 0 ? 'under' : 'over'}
              </div>
            )}
          </div>
          {isEditingSchedule ? (
            <>
              <table className="widefat fixed striped" style={{ marginTop: '10px', border: '1px solid #ccd0d4' }}>
                <thead>
                  <tr>
                    <th style={{ textAlign: 'left', padding: '8px' }}>Title</th>
                    <th style={{ textAlign: 'right', padding: '8px', width: '80px' }}>%</th>
                    <th style={{ textAlign: 'right', padding: '8px' }}>Amount</th>
                    <th style={{ textAlign: 'left', padding: '8px' }}>Due</th>
                    <th style={{ textAlign: 'left', padding: '8px', width: '80px' }}>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {scheduleDraft.map((m, index) => {
                    const isBalanceRow =
                      m.title === 'Balance on completion' &&
                      index === scheduleDraft.length - 1;

                    if (isBalanceRow && quote) {
                      const { quoteTotal } = computeQuoteTotals(quote);
                      const nonBalanceTotal = scheduleDraft.reduce(
                        (sum, row, rowIndex) =>
                          rowIndex === index
                            ? sum
                            : sum +
                              (Number.isFinite(row.amount) ? row.amount : 0),
                        0
                      );
                      const balanceAmount = quoteTotal - nonBalanceTotal;
                      const balancePercent =
                        quoteTotal > 0
                          ? (balanceAmount / quoteTotal) * 100
                          : 0;

                      return (
                        <tr key={m.id ?? index}>
                          <td style={{ padding: '8px' }}>
                            <input
                              type="text"
                              value="Balance on completion"
                              disabled
                              style={{ width: '100%', fontWeight: 600 }}
                            />
                          </td>
                          <td style={{ padding: '8px', textAlign: 'right' }}>
                            <input
                              type="number"
                              value={balancePercent.toFixed(2)}
                              disabled
                              style={{ width: '100%' }}
                            />
                          </td>
                          <td style={{ padding: '8px', textAlign: 'right' }}>
                            <input
                              type="number"
                              value={balanceAmount.toFixed(2)}
                              disabled
                              style={{ width: '100%' }}
                            />
                          </td>
                          <td style={{ padding: '8px' }}>
                            <input
                              type="date"
                              value={m.dueDate ? m.dueDate.substring(0, 10) : ''}
                              onChange={(e) => {
                                const next = [...scheduleDraft];
                                const value = e.target.value || null;
                                next[index] = {
                                  ...next[index],
                                  dueDate: value,
                                };
                                setScheduleDraft(next);
                              }}
                            />
                          </td>
                          <td style={{ padding: '8px' }} />
                        </tr>
                      );
                    }

                    return (
                      <tr key={m.id ?? index}>
                        <td style={{ padding: '8px' }}>
                          <input
                            type="text"
                            value={m.title}
                            onChange={(e) => {
                              const next = [...scheduleDraft];
                              next[index] = {
                                ...next[index],
                                title: e.target.value,
                              };
                              setScheduleDraft(next);
                            }}
                            style={{ width: '100%' }}
                          />
                        </td>
                        <td style={{ padding: '8px', textAlign: 'right' }}>
                          <input
                            type="number"
                            min={0}
                            max={100}
                            value={m.percentInput ?? ''}
                            onChange={(e) => {
                              const raw = e.target.value;
                              const next = [...scheduleDraft];
                              if (raw === '') {
                                next[index] = {
                                  ...next[index],
                                  percent: undefined,
                                  percentInput: '',
                                };
                                setScheduleDraft(next);
                                return;
                              }
                              const percentRaw = Number(raw);
                              const percent = Number.isFinite(percentRaw)
                                ? Math.round(percentRaw * 100) / 100
                                : undefined;
                              const { quoteTotal } = computeQuoteTotals(quote!);
                              const amount =
                                quoteTotal > 0 && typeof percent === 'number'
                                  ? (quoteTotal * percent) / 100
                                  : 0;
                              next[index] = {
                                ...next[index],
                                percent,
                                percentInput: raw,
                                amount,
                                source: 'percent',
                              };
                              setScheduleDraft(next);
                            }}
                            style={{
                              width: '100%',
                              borderColor:
                                m.source === 'percent' ? '#00a32a' : undefined,
                            }}
                          />
                        </td>
                        <td style={{ padding: '8px', textAlign: 'right' }}>
                          <input
                            type="number"
                            value={m.amount}
                            onChange={(e) => {
                              const next = [...scheduleDraft];
                              const raw = e.target.value;
                              const amount = Number(raw);
                              const { quoteTotal } = computeQuoteTotals(quote!);
                              let percent: number | undefined = undefined;
                              if (quoteTotal > 0 && Number.isFinite(amount)) {
                                const percentRaw = (amount / quoteTotal) * 100;
                                percent =
                                  Math.round(percentRaw * 100) / 100;
                              }
                              next[index] = {
                                ...next[index],
                                amount,
                                percent,
                                percentInput:
                                  typeof percent === 'number' &&
                                  Number.isFinite(percent)
                                    ? percent.toFixed(2)
                                    : '',
                                source: 'amount',
                              };
                              setScheduleDraft(next);
                            }}
                            style={{
                              width: '100%',
                              borderColor:
                                m.source === 'amount' ? '#00a32a' : undefined,
                            }}
                          />
                        </td>
                        <td style={{ padding: '8px' }}>
                          <input
                            type="date"
                            value={m.dueDate ? m.dueDate.substring(0, 10) : ''}
                            onChange={(e) => {
                              const next = [...scheduleDraft];
                              const value = e.target.value || null;
                              next[index] = {
                                ...next[index],
                                dueDate: value,
                              };
                              setScheduleDraft(next);
                            }}
                          />
                        </td>
                        <td style={{ padding: '8px' }}>
                          <button
                            type="button"
                            className="button button-small"
                            onClick={() => {
                              setScheduleDraft((current) =>
                                current.filter((_, i) => i !== index)
                              );
                            }}
                          >
                            Delete
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
              <div style={{ marginTop: '10px', display: 'flex', justifyContent: 'space-between' }}>
                <button
                  type="button"
                  className="button"
                  onClick={() =>
                    setScheduleDraft((current) => {
                      if (!quote) {
                        return current;
                      }

                      const { quoteTotal } = computeQuoteTotals(quote);
                      const nonBalance = current.filter(
                        (m) => m.title !== 'Balance on completion'
                      );
                      const balance =
                        current.find(
                          (m) => m.title === 'Balance on completion'
                        ) || null;

                      const scheduledTotal = nonBalance.reduce(
                        (sum, m) =>
                          sum +
                          (Number.isFinite(m.amount) ? m.amount : 0),
                        0
                      );

                      const outstanding = quoteTotal - scheduledTotal;
                      const amount =
                        Number.isFinite(outstanding) && outstanding > 0
                          ? outstanding
                          : 0;

                      const newRow: any = {
                        id: Date.now(),
                        title: '',
                        amount,
                        dueDate: null,
                        isPaid: false,
                        source: 'amount',
                      };

                      const next = [...nonBalance, newRow];

                      if (balance) {
                        next.push(balance);
                      } else {
                        next.push({
                          id: Date.now() + 1,
                          title: 'Balance on completion',
                          amount: 0,
                          dueDate: null,
                          isPaid: false,
                          source: 'amount',
                        });
                      }

                      return next;
                    })
                  }
                >
                  Add Row
                </button>
                <div>
                  <button
                    type="button"
                    className="button button-primary"
                    onClick={async () => {
                      try {
                        setLoading(true);

                        const { quoteTotal } = computeQuoteTotals(quote!);
                        const nonBalance = scheduleDraft.filter(
                          (m) => m.title !== 'Balance on completion'
                        );
                        const nonBalanceTotal = nonBalance.reduce(
                          (sum, m) =>
                            sum +
                            (Number.isFinite(m.amount) ? m.amount : 0),
                          0
                        );
                        const balanceAmount = Math.max(
                          quoteTotal - nonBalanceTotal,
                          0
                        );

                        const balanceRow = scheduleDraft.find(
                          (m) => m.title === 'Balance on completion'
                        );

                        const milestones = [
                          ...nonBalance.map((m) => ({
                            title: m.title,
                            amount: m.amount,
                            dueDate: m.dueDate,
                          })),
                          {
                            title: 'Balance on completion',
                            amount: balanceAmount,
                            dueDate: balanceRow ? balanceRow.dueDate : null,
                          },
                        ];
                        const response = await fetch(
                          `${window.petSettings.apiUrl}/quotes/${quoteId}/payment-schedule`,
                          {
                            method: 'POST',
                            headers: {
                              'X-WP-Nonce': window.petSettings.nonce,
                              'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ milestones }),
                          }
                        );
                        const payload = await response.json().catch(() => null);
                        if (!response.ok) {
                          const message =
                            payload && typeof payload.error === 'string'
                              ? payload.error
                              : 'Failed to save payment schedule';
                          throw new Error(message);
                        }
                        if (payload && typeof payload === 'object') {
                          setQuote(payload);
                        } else {
                          await fetchQuote();
                        }
                        setIsEditingSchedule(false);
                        toast.success('Payment schedule saved.');
                      } catch (err) {
                        toast.error(
                          err instanceof Error
                            ? err.message
                            : 'Error saving payment schedule'
                        );
                      } finally {
                        setLoading(false);
                      }
                    }}
                    style={{ marginRight: '8px' }}
                  >
                    Save Schedule
                  </button>
                  <button
                    type="button"
                    className="button"
                    onClick={() => {
                      if (quote && Array.isArray(quote.paymentSchedule)) {
                        const { quoteTotal } = computeQuoteTotals(quote);

                        const nonBalance = quote.paymentSchedule.filter(
                          (m: any) => m.title !== 'Balance on completion'
                        );
                        const existingBalance = quote.paymentSchedule.find(
                          (m: any) => m.title === 'Balance on completion'
                        );

                        const nonBalanceWithDerived = nonBalance.map(
                          (m: any) => {
                            let percent: number | undefined = undefined;
                            if (
                              quoteTotal > 0 &&
                              typeof m.amount === 'number'
                            ) {
                              percent = (m.amount / quoteTotal) * 100;
                            }
                            return {
                              ...m,
                              percent,
                              source: 'amount' as const,
                            };
                          }
                        );

                        const nonBalanceTotal = nonBalance.reduce(
                          (sum: number, m: any) =>
                            sum +
                            (typeof m.amount === 'number' ? m.amount : 0),
                          0
                        );
                        const balanceAmount = Math.max(
                          quoteTotal - nonBalanceTotal,
                          0
                        );

                        const balancePercent =
                          quoteTotal > 0
                            ? (balanceAmount / quoteTotal) * 100
                            : undefined;

                        const balanceRow: any = {
                          id: existingBalance?.id ?? Date.now(),
                          title: 'Balance on completion',
                          amount: balanceAmount,
                          dueDate: existingBalance?.dueDate ?? null,
                          isPaid: existingBalance?.isPaid ?? false,
                          percent: balancePercent,
                          source: 'amount' as const,
                        };

                        setScheduleDraft([
                          ...nonBalanceWithDerived,
                          balanceRow,
                        ]);
                      } else {
                        setScheduleDraft([]);
                      }
                      setIsEditingSchedule(false);
                    }}
                  >
                    Cancel
                  </button>
                </div>
              </div>
            </>
          ) : (
            <>
              <table className="widefat fixed striped" style={{ marginTop: '10px', border: '1px solid #ccd0d4' }}>
                <thead>
                  <tr>
                    <th style={{ textAlign: 'left', padding: '8px' }}>Title</th>
                    <th style={{ textAlign: 'right', padding: '8px' }}>Amount</th>
                    <th style={{ textAlign: 'left', padding: '8px' }}>Due</th>
                    <th style={{ textAlign: 'left', padding: '8px' }}>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {quote.paymentSchedule.map((m) => (
                    <tr key={m.id}>
                      <td style={{ padding: '8px' }}>{m.title}</td>
                      <td style={{ padding: '8px', textAlign: 'right' }}>
                        ${m.amount.toFixed(2)}
                      </td>
                      <td style={{ padding: '8px' }}>
                        {m.dueDate
                          ? new Date(m.dueDate).toLocaleDateString()
                          : 'On acceptance'}
                      </td>
                      <td style={{ padding: '8px' }}>
                        {m.isPaid ? 'Paid' : 'Unpaid'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <div style={{ marginTop: '10px', textAlign: 'right' }}>
                <button
                  type="button"
                  className="button"
                  onClick={() => setIsEditingSchedule(true)}
                >
                  Edit Schedule
                </button>
              </div>
            </>
          )}
        </div>
      )}

      <div style={{ marginTop: '20px', position: 'relative', minHeight: '60px' }}>
        <button
          type="button"
          aria-label="Add Section"
          onClick={() => setShowFabMenu((open) => !open)}
          style={{
            position: 'fixed',
            bottom: '40px',
            right: '40px',
            width: '56px',
            height: '56px',
            borderRadius: '50%',
            border: 'none',
            backgroundColor: '#2271b1',
            color: '#fff',
            fontSize: '28px',
            boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
            cursor: 'pointer',
            zIndex: 10,
          }}
        >
          +
        </button>

        {showFabMenu && (
          <div
            className="card"
            style={{
              position: 'fixed',
              bottom: '110px',
              right: '40px',
              background: '#fff',
              border: '1px solid #ccd0d4',
              boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
              borderRadius: '4px',
              zIndex: 11,
              minWidth: '220px',
            }}
          >
            <div style={{ padding: '8px 12px', borderBottom: '1px solid #eee', fontWeight: 600 }}>
              Add to quote
            </div>
            <button
              type="button"
              className="button-link"
              style={{
                display: 'block',
                width: '100%',
                textAlign: 'left',
                padding: '8px 12px',
                borderBottom: '1px solid #f0f0f0',
                background: 'transparent',
              }}
              onClick={() => {
                setShowFabMenu(false);
                handleAddSection();
              }}
            >
              Section
            </button>
            <button
              type="button"
              className="button-link"
              style={{
                display: 'block',
                width: '100%',
                textAlign: 'left',
                padding: '8px 12px',
                borderBottom: '1px solid #f0f0f0',
                background: 'transparent',
              }}
              onClick={() => {
                setShowFabMenu(false);
                handleCreateBlockForType('PriceAdjustmentBlock');
              }}
            >
              Cost Adjustment (whole quote)
            </button>
            <button
              type="button"
              className="button-link"
              style={{
                display: 'block',
                width: '100%',
                textAlign: 'left',
                padding: '8px 12px',
                borderBottom: '1px solid #f0f0f0',
                background: 'transparent',
              }}
              onClick={() => {
                setShowFabMenu(false);
                handleAddPaymentSchedule();
              }}
            >
              Payment schedule (whole quote)
            </button>
            <button
              type="button"
              className="button-link"
              style={{
                display: 'block',
                width: '100%',
                textAlign: 'left',
                padding: '8px 12px',
                borderBottom: '1px solid #f0f0f0',
                background: 'transparent',
              }}
              onClick={() => {
                setShowFabMenu(false);
                handleAddTextSectionWithBlock();
              }}
            >
              Text section
            </button>
            <button
              type="button"
              className="button-link"
              style={{
                display: 'block',
                width: '100%',
                textAlign: 'left',
                padding: '8px 12px',
                background: 'transparent',
                color: '#666',
                cursor: 'default',
              }}
              disabled
            >
              Document (coming soon)
            </button>
          </div>
        )}

        {showTypeSelection && blockSectionIdForCreate !== null && (
          <div
            className="card"
            style={{
              padding: '20px',
              background: '#fff',
              border: '1px solid #ccd0d4',
              maxWidth: '600px',
              position: 'fixed',
              bottom: '120px',
              right: '40px',
              zIndex: 11,
            }}
          >
            <h3>Select Block Type</h3>
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: '1fr 1fr',
                gap: '15px',
                marginTop: '15px',
              }}
            >
              <button
                className="button"
                onClick={() => handleCreateBlockForType('HardwareBlock')}
              >
                Once-off Product
                <span
                  style={{
                    display: 'block',
                    fontSize: '0.8em',
                    color: '#666',
                    marginTop: '5px',
                  }}
                >
                  One-time product or license
                </span>
              </button>
              <button
                className="button"
                onClick={() => handleCreateBlockForType('OnceOffSimpleServiceBlock')}
              >
                Once-off Simple Services
                <span
                  style={{
                    display: 'block',
                    fontSize: '0.8em',
                    color: '#666',
                    marginTop: '5px',
                  }}
                >
                  One-time labor or fee
                </span>
              </button>
              <button
                className="button"
                onClick={() => handleCreateBlockForType('OnceOffProjectBlock')}
              >
                Once-off Project
                <span
                  style={{
                    display: 'block',
                    fontSize: '0.8em',
                    color: '#666',
                    marginTop: '5px',
                  }}
                >
                  Multi-phase project
                </span>
              </button>
              <button
                className="button"
                onClick={() => handleCreateBlockForType('RepeatHardwareBlock')}
              >
                Repeat Product
                <span
                  style={{
                    display: 'block',
                    fontSize: '0.8em',
                    color: '#666',
                    marginTop: '5px',
                  }}
                >
                  Subscription product/license
                </span>
              </button>
              <button
                className="button"
                onClick={() => handleCreateBlockForType('RepeatServiceBlock')}
              >
                Repeat Services
                <span
                  style={{
                    display: 'block',
                    fontSize: '0.8em',
                    color: '#666',
                    marginTop: '5px',
                  }}
                >
                  Recurring service
                </span>
              </button>
              <button
                className="button"
                onClick={() => handleCreateBlockForType('PriceAdjustmentBlock')}
              >
                Price Adjustment
                <span
                  style={{
                    display: 'block',
                    fontSize: '0.8em',
                    color: '#666',
                    marginTop: '5px',
                  }}
                >
                  Section or quote adjustment
                </span>
              </button>
              <button
                className="button"
                onClick={() => handleCreateBlockForType('TextBlock')}
              >
                Text Block
                <span
                  style={{
                    display: 'block',
                    fontSize: '0.8em',
                    color: '#666',
                    marginTop: '5px',
                  }}
                >
                  Non-priced text
                </span>
              </button>
            </div>
            <div style={{ marginTop: '15px', textAlign: 'right' }}>
              <button className="button" onClick={handleCancelBlockTypeSelection}>
                Cancel
              </button>
            </div>
          </div>
        )}
      </div>
      <ConfirmationDialog
        open={pendingConfirmation !== null}
        title={pendingConfirmation?.title || 'Confirm action'}
        description={pendingConfirmation?.description || ''}
        confirmLabel={pendingConfirmation?.confirmLabel || 'Confirm'}
        onCancel={() => closeConfirmation(false)}
        onConfirm={() => closeConfirmation(true)}
      />

    </div>
  );
};

export default QuoteDetails;
