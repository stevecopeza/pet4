import React, { useEffect, useRef, useState } from 'react';
import { Quote, QuoteBlock, QuoteSection, Employee, Team } from '../types';

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
import ConversationPanel from './ConversationPanel';

interface MarkdownTextareaProps {
  value: string;
  onChange: (value: string) => void;
}

const MarkdownTextarea: React.FC<MarkdownTextareaProps> = ({ value, onChange }) => {
  const textareaRef = useRef<HTMLTextAreaElement | null>(null);

  const applyWrap = (marker: string, placeholder: string) => {
    const el = textareaRef.current;
    if (!el) return;
    const start = el.selectionStart ?? 0;
    const end = el.selectionEnd ?? start;
    const selected = value.slice(start, end) || placeholder;
    const wrapped = `${marker}${selected}${marker}`;
    const next =
      value.slice(0, start) + wrapped + value.slice(end);
    onChange(next);
    const selectionStart = start + marker.length;
    const selectionEnd = selectionStart + selected.length;
    requestAnimationFrame(() => {
      el.focus();
      el.setSelectionRange(selectionStart, selectionEnd);
    });
  };

  const applyList = () => {
    const el = textareaRef.current;
    if (!el) return;
    const start = el.selectionStart ?? 0;
    const end = el.selectionEnd ?? start;
    const before = value.slice(0, start);
    const selected = value.slice(start, end);
    const after = value.slice(end);
    const text = selected || 'List item';
    const lines = text.split('\n').map((line) => {
      const trimmed = line.trim();
      if (!trimmed) return '- ';
      if (trimmed.startsWith('- ')) return trimmed;
      return `- ${trimmed}`;
    });
    const block = lines.join('\n');
    const next = before + block + after;
    onChange(next);
    const selectionStart = before.length;
    const selectionEnd = selectionStart + block.length;
    requestAnimationFrame(() => {
      el.focus();
      el.setSelectionRange(selectionStart, selectionEnd);
    });
  };

  const applyHeading = () => {
    const el = textareaRef.current;
    if (!el) return;
    const start = el.selectionStart ?? 0;
    const end = el.selectionEnd ?? start;
    const before = value.slice(0, start);
    const selected = value.slice(start, end) || 'Heading';
    const after = value.slice(end);
    const prefix = '# ';
    const block = `${prefix}${selected}`;
    const next = before + block + after;
    onChange(next);
    const selectionStart = before.length + prefix.length;
    const selectionEnd = selectionStart + selected.length;
    requestAnimationFrame(() => {
      el.focus();
      el.setSelectionRange(selectionStart, selectionEnd);
    });
  };

  const applyQuote = () => {
    const el = textareaRef.current;
    if (!el) return;
    const start = el.selectionStart ?? 0;
    const end = el.selectionEnd ?? start;
    const before = value.slice(0, start);
    const selected = value.slice(start, end) || 'Quoted text';
    const after = value.slice(end);
    const lines = selected.split('\n').map((line) => `> ${line || ''}`);
    const block = lines.join('\n');
    const next = before + block + after;
    onChange(next);
    const selectionStart = before.length + 2;
    const selectionEnd = selectionStart + selected.length;
    requestAnimationFrame(() => {
      el.focus();
      el.setSelectionRange(selectionStart, selectionEnd);
    });
  };

  const applyLink = () => {
    const el = textareaRef.current;
    if (!el) return;
    const start = el.selectionStart ?? 0;
    const end = el.selectionEnd ?? start;
    const selected = value.slice(start, end) || 'link text';
    const block = `[${selected}](https://example.com)`;
    const next = value.slice(0, start) + block + value.slice(end);
    onChange(next);
    const selectionStart = start + 1;
    const selectionEnd = selectionStart + selected.length;
    requestAnimationFrame(() => {
      el.focus();
      el.setSelectionRange(selectionStart, selectionEnd);
    });
  };

  return (
    <div>
      <div
        style={{
          marginBottom: '6px',
          display: 'flex',
          flexWrap: 'wrap',
          gap: '4px',
          alignItems: 'center',
        }}
      >
        <button
          type="button"
          className="button button-small"
          title="Bold"
          aria-label="Bold"
          onClick={() => applyWrap('**', 'bold text')}
        >
          B
        </button>
        <button
          type="button"
          className="button button-small"
          title="Italic"
          aria-label="Italic"
          onClick={() => applyWrap('_', 'italic text')}
        >
          I
        </button>
        <button
          type="button"
          className="button button-small"
          title="Heading"
          aria-label="Heading"
          onClick={applyHeading}
        >
          H
        </button>
        <button
          type="button"
          className="button button-small"
          title="Bulleted list"
          aria-label="Bulleted list"
          onClick={applyList}
        >
          • List
        </button>
        <button
          type="button"
          className="button button-small"
          title="Quote"
          aria-label="Quote"
          onClick={applyQuote}
        >
          “”
        </button>
        <button
          type="button"
          className="button button-small"
          title="Link"
          aria-label="Link"
          onClick={applyLink}
        >
          Link
        </button>
        <span
          style={{
            fontSize: '11px',
            color: '#666',
            marginLeft: '6px',
            whiteSpace: 'nowrap',
          }}
        >
          Formatting: Markdown
        </span>
      </div>
      <textarea
        ref={textareaRef}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        rows={6}
        style={{ width: '100%' }}
      />
    </div>
  );
};

const generateLocalId = () =>
  `id_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`;

export const computeQuoteTotals = (
  quote: Quote
): { quoteTotal: number; sectionTotals: Record<number, number> } => {
  const sections = (quote.sections || []).slice();
  const blocks = (quote.blocks || []).slice();

  const sectionTotals: Record<number, number> = {};
  let rootPricedTotal = 0;
  let quoteScopedAdjustmentsTotal = 0;

  const getBaseBlockValue = (block: QuoteBlock): number => {
    if (!block.priced) {
      return 0;
    }

    const payload = block.payload || {};
    const value =
      typeof payload.totalValue === 'number'
        ? payload.totalValue
        : typeof payload.sellValue === 'number'
        ? payload.sellValue
        : 0;

    return value;
  };

  sections.forEach((section) => {
    const blocksInSection = blocks.filter(
      (block) => block.sectionId === section.id
    );

    const nonAdjustmentBlocks = blocksInSection.filter(
      (block) => block.type !== 'PriceAdjustmentBlock'
    );

    const hasNonAdjustmentBlocks = nonAdjustmentBlocks.some(
      (block) => block.priced
    );

    const baseTotal = nonAdjustmentBlocks.reduce(
      (sum, block) => sum + getBaseBlockValue(block),
      0
    );

    let sectionAdjustmentTotal = 0;

    blocksInSection.forEach((block) => {
      if (block.type === 'PriceAdjustmentBlock') {
        const payload = block.payload || {};
        const rawAmount =
          typeof payload.amount === 'number'
            ? payload.amount
            : typeof payload.amount === 'string'
            ? parseFloat(payload.amount)
            : 0;

        const amount = Number.isFinite(rawAmount) ? rawAmount : 0;

        if (hasNonAdjustmentBlocks) {
          sectionAdjustmentTotal += amount;
        } else {
          quoteScopedAdjustmentsTotal += amount;
        }
      }
    });

    sectionTotals[section.id] = baseTotal + sectionAdjustmentTotal;
  });

  const rootBlocks = blocks.filter((block) => block.sectionId === null);

  rootBlocks.forEach((block) => {
    if (block.type === 'PriceAdjustmentBlock') {
      const payload = block.payload || {};
      const rawAmount =
        typeof payload.amount === 'number'
          ? payload.amount
          : typeof payload.amount === 'string'
          ? parseFloat(payload.amount)
          : 0;

      const amount = Number.isFinite(rawAmount) ? rawAmount : 0;

      quoteScopedAdjustmentsTotal += amount;
    } else {
      rootPricedTotal += getBaseBlockValue(block);
    }
  });

  const sectionsTotal = sections.reduce(
    (sum, section) => sum + (sectionTotals[section.id] ?? 0),
    0
  );

  const quoteTotal =
    sectionsTotal + rootPricedTotal + quoteScopedAdjustmentsTotal;

  return { quoteTotal, sectionTotals };
};

const isQuoteLevelAdjustmentSection = (
  section: QuoteSection,
  blocks: QuoteBlock[]
): boolean => {
  const blocksInSection = blocks.filter(
    (block) => block.sectionId === section.id
  );

  if (blocksInSection.length === 0) {
    return false;
  }

  return blocksInSection.every(
    (block) => block.type === 'PriceAdjustmentBlock'
  );
};

interface QuoteDetailsProps {
  quoteId: number;
  onBack: () => void;
}

const QuoteDetails: React.FC<QuoteDetailsProps> = ({ quoteId, onBack }) => {
  console.log('QuoteDetails rendering for ID:', quoteId);
  const [quote, setQuote] = useState<Quote | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeSchema, setActiveSchema] = useState<any | null>(null);
  const [catalogItems, setCatalogItems] = useState<{ id: number; name: string; unit_price: number; unit_cost: number; type: string }[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  
  const [showTypeSelection, setShowTypeSelection] = useState(false);
  const [blockSectionIdForCreate, setBlockSectionIdForCreate] = useState<number | null>(null);
  const [showFabMenu, setShowFabMenu] = useState(false);
  const [showAdjustmentForm, setShowAdjustmentForm] = useState(false);
  const [expandedBlockId, setExpandedBlockId] = useState<number | null>(null);
  const [blockDrafts, setBlockDrafts] = useState<Record<number, any>>({});
  const [savingBlockId, setSavingBlockId] = useState<number | null>(null);
  const [editingSectionId, setEditingSectionId] = useState<number | null>(null);
  const [sectionDraftNames, setSectionDraftNames] = useState<Record<number, string>>({});
  const [sectionMenuOpenId, setSectionMenuOpenId] = useState<number | null>(null);
  const [conversationContext, setConversationContext] = useState<{
    type: string;
    id: string;
    version: string;
    subject: string;
    subjectKey: string;
  } | null>(null);

  const blocksForRendering: QuoteBlock[] = (quote?.blocks || []).slice().sort((a, b) => a.orderIndex - b.orderIndex);
  const sectionsForRendering: QuoteSection[] = (quote?.sections || [])
    .slice()
    .sort((a, b) => {
      const aIsAdjustment = isQuoteLevelAdjustmentSection(
        a,
        blocksForRendering
      );
      const bIsAdjustment = isQuoteLevelAdjustmentSection(
        b,
        blocksForRendering
      );

      if (aIsAdjustment !== bIsAdjustment) {
        return aIsAdjustment ? 1 : -1;
      }

      return a.orderIndex - b.orderIndex;
    });

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
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}`, {
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch quote details');
      }

      const data = await response.json();
      console.log('Fetched quote data:', data);
      console.log('Quote components:', data.components);
      console.log('Quote sections:', data.sections);
      setQuote(data);
      setExpandedBlockId(null);
      setBlockDrafts({});
      setSavingBlockId(null);
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

  useEffect(() => {
    console.log('QuoteDetails mounted/updated. Fetching data...');
    fetchQuote();
    fetchSchema();
    fetchCatalog();
    fetchEmployees();
    fetchTeams();
    return () => console.log('QuoteDetails unmounting');
  }, [quoteId]);

  const handleAddPaymentSchedule = async () => {
    if (!quote) {
      return;
    }

    if (
      Array.isArray((quote as any).paymentSchedule) &&
      (quote as any).paymentSchedule.length > 0
    ) {
      const replace = confirm(
        'A payment schedule already exists for this quote.\n\nDo you want to replace it with a single full-payment schedule based on the current quote total?'
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
    } catch (err) {
      alert(
        err instanceof Error
          ? err.message
          : 'Error setting payment schedule'
      );
    } finally {
      setLoading(false);
    }
  };

  const handleSend = async () => {
    if (!confirm('Are you sure you want to send this quote?')) return;
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}/send`, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.error || 'Failed to send quote');
      }
      await fetchQuote();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error sending quote');
      setLoading(false);
    }
  };

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
    if (!confirm('Are you sure you want to mark this quote as ACCEPTED? This will create a project.')) return;
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}/accept`, {
        method: 'POST',
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.error || 'Failed to accept quote');
      }
      await fetchQuote();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error accepting quote');
      setLoading(false);
    }
  };

  const handleAdjustmentAdded = () => {
    setShowAdjustmentForm(false);
    fetchQuote();
  };

  const handleRemoveAdjustment = async (adjustmentId: number) => {
    if (!confirm('Are you sure you want to remove this adjustment?')) return;
    try {
      setLoading(true);
      const response = await fetch(`${window.petSettings.apiUrl}/quotes/${quoteId}/adjustments/${adjustmentId}`, {
        method: 'DELETE',
        headers: {
          'X-WP-Nonce': window.petSettings.nonce,
        },
      });
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.error || 'Failed to remove adjustment');
      }
      await fetchQuote();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error removing adjustment');
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
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error adding block');
    }
  };

  const handleDeleteBlock = async (blockId: number) => {
    if (!confirm('Are you sure you want to delete this block?')) {
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

      await fetchQuote();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error deleting block');
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

      await fetchQuote();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error renaming section');
    } finally {
      setEditingSectionId(null);
    }
  };

  const toggleSectionMenu = (sectionId: number) => {
    setSectionMenuOpenId((current) => (current === sectionId ? null : sectionId));
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

      await fetchQuote();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error updating section settings');
    } finally {
      setSectionMenuOpenId(null);
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

      await fetchQuote();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error cloning section');
    } finally {
      setSectionMenuOpenId(null);
    }
  };

  const handleDeleteSection = async (sectionId: number, hasNonTextBlocks: boolean) => {
    if (hasNonTextBlocks) {
      alert('Cannot delete a section that contains non-text blocks.');
      return;
    }

    if (!confirm('Are you sure you want to delete this empty section?')) {
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

      await fetchQuote();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error deleting section');
    } finally {
      setSectionMenuOpenId(null);
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
      } else if (block.type === 'OnceOffSimpleServiceBlock') {
        draft.catalogItemId = payload.catalogItemId ?? null;
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
      const totalValue = quantity * sellValue;
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
      };
    } else if (block.type === 'OnceOffProjectBlock') {
      const rawPhases = Array.isArray(draft.phases) ? draft.phases : [];

      const phases = rawPhases.map((phase: any, index: number) => {
        const phaseId =
          typeof phase.id === 'string' && phase.id.length > 0
            ? phase.id
            : generateLocalId();

        const unitsRaw = Array.isArray(phase.units) ? phase.units : [];
        let phaseTotalValue = 0;

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
          };
        });

        return {
          id: phaseId,
          name: phase.name ?? '',
          order: typeof phase.order === 'number' ? phase.order : index,
          units,
          phaseTotalValue,
        };
      });

      const totalValue = phases.reduce(
        (sum: number, phase: any) => sum + (phase.phaseTotalValue || 0),
        0
      );

      normalizedPayload = {
        description: draft.description ?? '',
        phases,
        totalValue,
      };
    } else if (block.type === 'HardwareBlock') {
      const quantity = Number.isFinite(Number(draft.quantity))
        ? Number(draft.quantity)
        : 1;
      const unitPrice = Number.isFinite(Number(draft.unitPrice))
        ? Number(draft.unitPrice)
        : 0;
      const totalValue = quantity * unitPrice;
      normalizedPayload = {
        catalogItemId:
          draft.catalogItemId !== undefined ? draft.catalogItemId : null,
        description: draft.description ?? '',
        quantity,
        unitPrice,
        totalValue,
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

      setExpandedBlockId(null);
      setSavingBlockId(null);
      await fetchQuote();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Error updating block');
      setSavingBlockId(null);
    }
  };

  const cancelBlockEdit = (blockId: number) => {
    setExpandedBlockId((current) => (current === blockId ? null : current));
    setBlockDrafts((prev) => {
      const next = { ...prev };
      delete next[blockId];
      return next;
    });
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
      console.log('Adding quote section for quote', quoteId);
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
      console.log('Add section response status', response.status);
      const payload = await response.json().catch(() => null);
      console.log('Add section response body', payload);
      if (!response.ok) {
        const message =
          payload && typeof payload.error === 'string'
            ? payload.error
            : 'Failed to add section';
        throw new Error(message);
      }
      await fetchQuote();
    } catch (err) {
      console.error('Error adding section', err);
      alert(
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
      alert(
        err instanceof Error
          ? err.message
          : 'Error adding text section'
      );
    }
  };

  if (loading) return <div>Loading quote details...</div>;
  if (error) return <div style={{ color: 'red' }}>Error: {error}</div>;
  if (!quote) return <div>Quote not found</div>;

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

  return (
    <div className="pet-quote-details">
      <div style={{ marginBottom: '20px' }}>
        <button className="button" onClick={onBack}>&larr; Back to Quotes</button>
      </div>

      <div className="card" style={{ padding: '20px', marginBottom: '20px', background: '#fff', border: '1px solid #ccd0d4' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
          <h2>Quote #{quote.id} (v{quote.version})</h2>
          <button
            className="button"
            onClick={() => setConversationContext({
              type: 'quote',
              id: quote.id.toString(),
              version: quote.version.toString(),
              subject: `Quote #${quote.id}: ${quote.title}`,
              subjectKey: `quote:${quote.id}`
            })}
          >
            Discuss Quote
          </button>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
          <div>
            <p><strong>Customer ID:</strong> {quote.customerId}</p>
            <p><strong>Title:</strong> {quote.title}</p>
            {quote.description && (
              <p>
                <strong>Description:</strong> {quote.description}
              </p>
            )}
            <p>
              <strong>State:</strong>{' '}
              <span
                className={`pet-status-badge status-${quote.state.toLowerCase()}`}
              >
                {quote.state}
              </span>
            </p>
            {quote.state === 'draft' && (
              <p style={{ fontSize: '12px', color: '#666', marginTop: '4px' }}>
                Changes are saved automatically. You can return to this draft
                later without sending it.
              </p>
            )}
            <div style={{ marginTop: '10px' }}>
              {quote.state === 'draft' && (
                <div>
                  <button
                    className="button"
                    onClick={handleSaveDraft}
                    disabled={loading}
                    style={{ marginRight: '8px' }}
                  >
                    Save Draft
                  </button>
                  <button
                    className="button"
                    onClick={handleSend}
                    disabled={!isReady}
                    title={
                      !isReady
                        ? readinessIssues.join('\n')
                        : 'Send to customer'
                    }
                    style={{ opacity: !isReady ? 0.5 : 1 }}
                  >
                    Send Quote
                  </button>
                  {!isReady && (
                    <div
                      style={{
                        color: '#d63638',
                        fontSize: '12px',
                        marginTop: '5px',
                      }}
                    >
                      <strong>Not ready to send:</strong>
                      <ul style={{ margin: '5px 0 0 15px' }}>
                        {readinessIssues.map((issue, i) => (
                          <li key={i}>{issue}</li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>
              )}
              {quote.state === 'sent' && (
                <button className="button button-primary" onClick={handleAccept}>Accept Quote</button>
              )}
            </div>
          </div>
          <div>
            <p><strong>Total Value:</strong> ${quoteTotal.toFixed(2)}</p>
            <p><strong>Base Cost:</strong> ${totalInternalCost.toFixed(2)}</p>
            {quote.costAdjustments && quote.costAdjustments.length > 0 && (
              <p><strong>Adjusted Cost:</strong> ${adjustedCost.toFixed(2)}</p>
            )}
            <p>
              <strong>Margin:</strong>{' '}
              <span style={{ color: margin < 0 ? 'red' : 'green' }}>
                ${margin.toFixed(2)}
              </span>
            </p>
            <p><strong>Components:</strong> {(quote.components || []).length}</p>
            {!quote.costAdjustments?.length && !showAdjustmentForm && (
              <p style={{ marginTop: '8px' }}>
                <button
                  type="button"
                  className="button button-small"
                  onClick={() => setShowAdjustmentForm(true)}
                >
                  Add Cost Adjustment
                </button>
              </p>
            )}
          </div>
        </div>

        {activeSchema && quote.malleableData && (
          <MalleableFieldsRenderer 
            schema={activeSchema} 
            values={quote.malleableData} 
            onChange={() => {}} 
            readOnly={true}
          />
        )}
      </div>

      <h3>Quote Sections</h3>
      {sectionsForRendering.length > 0 && (
        <div>
          {sectionsForRendering.map((section) => {
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
                    <button
                      type="button"
                      onClick={() => toggleSectionMenu(section.id)}
                      style={{
                        background: 'transparent',
                        border: 'none',
                        color: '#fff',
                        cursor: 'pointer',
                        fontSize: '16px',
                        lineHeight: 1,
                      }}
                      aria-label="Section options"
                    >
                      ⋯
                    </button>
                    {sectionMenuOpenId === section.id && (
                      <div
                        style={{
                          position: 'absolute',
                          marginTop: '24px',
                          background: '#fff',
                          color: '#000',
                          border: '1px solid #ccd0d4',
                          padding: '8px',
                          zIndex: 10,
                          minWidth: '180px',
                        }}
                      >
                        <button
                          type="button"
                          className="button button-link"
                          onClick={() => handleCloneSection(section.id)}
                        >
                          Clone section
                        </button>
                        <button
                          type="button"
                          className="button button-link"
                          onClick={() => handleDeleteSection(section.id, hasNonTextBlocks)}
                          disabled={hasNonTextBlocks}
                          title={
                            hasNonTextBlocks
                              ? 'Cannot delete a section that contains non-text blocks.'
                              : undefined
                          }
                        >
                          Delete section
                        </button>
                        <div style={{ marginTop: '6px', fontSize: '11px' }}>
                          <label style={{ display: 'block' }}>
                            <input
                              type="checkbox"
                              checked={section.showTotalValue}
                              onChange={() =>
                                handleSectionToggle(section.id, 'showTotalValue')
                              }
                            />{' '}
                            Show total value
                          </label>
                          <label style={{ display: 'block' }}>
                            <input
                              type="checkbox"
                              checked={section.showItemCount}
                              onChange={() =>
                                handleSectionToggle(section.id, 'showItemCount')
                              }
                            />{' '}
                            Show item count
                          </label>
                          <label style={{ display: 'block' }}>
                            <input
                              type="checkbox"
                              checked={section.showTotalHours}
                              onChange={() =>
                                handleSectionToggle(section.id, 'showTotalHours')
                              }
                            />{' '}
                            Show total hours
                          </label>
                        </div>
                      </div>
                    )}
                  </div>
                  {section.showTotalValue && (
                    <span style={{ fontSize: '11px', opacity: 0.8 }}>
                      Section Total: ${sectionTotal.toFixed(2)}
                    </span>
                  )}
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
                            <div style={{ whiteSpace: 'pre-wrap' }}>
                              {text || 'Empty text block'}
                            </div>
                            <div style={{ marginTop: '6px', textAlign: 'right' }}>
                              <button
                                className="button button-small"
                                onClick={() =>
                                  isExpanded
                                    ? cancelBlockEdit(block.id)
                                    : openBlockEditor(block)
                                }
                              >
                                {isExpanded ? 'Close' : 'Edit'}
                              </button>
                              <button
                                className="button button-small"
                                style={{ marginLeft: '8px' }}
                                disabled={isExpanded}
                                title={
                                  isExpanded
                                    ? 'Cannot delete while editing'
                                    : undefined
                                }
                                onClick={() => handleDeleteBlock(block.id)}
                              >
                                Delete
                              </button>
                            </div>
                            {isExpanded && (
                              <div
                                style={{
                                  marginTop: '10px',
                                  padding: '10px',
                                  background: '#f8f9fa',
                                  border: '1px solid #e2e4e7',
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
                        <th
                          style={{
                            textAlign: 'left',
                            padding: '10px',
                            width: '15%',
                          }}
                        >
                          Type
                        </th>
                        <th
                          style={{
                            textAlign: 'left',
                            padding: '10px',
                            width: '60%',
                          }}
                        >
                          Details
                        </th>
                        <th
                          style={{
                            textAlign: 'right',
                            padding: '10px',
                            width: '15%',
                          }}
                        >
                          Value
                        </th>
                        <th
                          style={{
                            textAlign: 'right',
                            padding: '10px',
                            width: '10%',
                          }}
                        >
                          Actions
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      {blocksInSection.map((block) => {
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
                        } else if (payload && typeof payload.description === 'string') {
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
                                {block.type === 'OnceOffProjectBlock' ? (
                                  (() => {
                                    const phases = Array.isArray(payload.phases)
                                      ? payload.phases
                                      : [];
                                    const baseDescription =
                                      typeof payload.description === 'string'
                                        ? payload.description
                                        : '';

                                    return (
                                      <div>
                                        {baseDescription && (
                                          <div style={{ fontWeight: 600 }}>
                                            {baseDescription}
                                          </div>
                                        )}
                                        {phases.map(
                                          (phase: any, phaseIndex: number) => {
                                            const phaseName =
                                              phase.name && phase.name.length > 0
                                                ? phase.name
                                                : `Phase ${phaseIndex + 1}`;
                                            const units = Array.isArray(
                                              phase.units
                                            )
                                              ? phase.units
                                              : [];

                                            return (
                                              <div
                                                key={phase.id || phaseIndex}
                                                style={{ marginTop: '8px' }}
                                              >
                                                <div
                                                  style={{
                                                    fontWeight: 600,
                                                    background: '#000',
                                                    color: '#fff',
                                                    padding: '4px 8px',
                                                  }}
                                                >
                                                  {phaseName}
                                                </div>
                                                {units.length > 0 && (
                                                  <table
                                                    style={{
                                                      width: '100%',
                                                      borderCollapse: 'collapse',
                                                      marginTop: '4px',
                                                    }}
                                                  >
                                                    <thead>
                                                      <tr>
                                                        <th
                                                          style={{
                                                            textAlign: 'left',
                                                            padding: '4px 8px',
                                                          }}
                                                        >
                                                          Item
                                                        </th>
                                                        <th
                                                          style={{
                                                            textAlign: 'right',
                                                            padding: '4px 8px',
                                                            width: '70px',
                                                          }}
                                                        >
                                                          Qty
                                                        </th>
                                                        <th
                                                          style={{
                                                            textAlign: 'right',
                                                            padding: '4px 8px',
                                                            width: '100px',
                                                          }}
                                                        >
                                                          Unit
                                                        </th>
                                                        <th
                                                          style={{
                                                            textAlign: 'right',
                                                            padding: '4px 8px',
                                                            width: '100px',
                                                          }}
                                                        >
                                                          Value
                                                        </th>
                                                      </tr>
                                                    </thead>
                                                    <tbody>
                                                      {units.map(
                                                        (
                                                          unit: any,
                                                          unitIndex: number
                                                        ) => {
                                                          const quantity =
                                                            Number.isFinite(
                                                              Number(
                                                                unit.quantity
                                                              )
                                                            )
                                                              ? Number(
                                                                  unit.quantity
                                                                )
                                                              : 0;
                                                          const unitPrice =
                                                            Number.isFinite(
                                                              Number(
                                                                unit.unitPrice
                                                              )
                                                            )
                                                              ? Number(
                                                                  unit
                                                                    .unitPrice
                                                                )
                                                              : 0;
                                                          const totalValue =
                                                            Number.isFinite(
                                                              Number(
                                                                unit.totalValue
                                                              )
                                                            )
                                                              ? Number(
                                                                  unit
                                                                    .totalValue
                                                                )
                                                              : quantity *
                                                                unitPrice;
                                                          const label =
                                                            unit.description &&
                                                            unit.description
                                                              .length > 0
                                                              ? unit.description
                                                              : `Unit ${
                                                                  unitIndex + 1
                                                                }`;
                                                          const isEvenRow =
                                                            unitIndex % 2 === 0;

                                                          return (
                                                            <tr
                                                              key={
                                                                unit.id ||
                                                                unitIndex
                                                              }
                                                              style={{
                                                                backgroundColor:
                                                                  isEvenRow
                                                                    ? '#f9f9f9'
                                                                    : '#ffffff',
                                                              }}
                                                            >
                                                              <td
                                                                style={{
                                                                  padding:
                                                                    '4px 8px',
                                                                }}
                                                              >
                                                                {label}
                                                              </td>
                                                              <td
                                                                style={{
                                                                  padding:
                                                                    '4px 8px',
                                                                  textAlign:
                                                                    'right',
                                                                }}
                                                              >
                                                                {quantity}
                                                              </td>
                                                              <td
                                                                style={{
                                                                  padding:
                                                                    '4px 8px',
                                                                  textAlign:
                                                                    'right',
                                                                  whiteSpace:
                                                                    'nowrap',
                                                                }}
                                                              >
                                                                $
                                                                {unitPrice.toFixed(
                                                                  2
                                                                )}
                                                              </td>
                                                              <td
                                                                style={{
                                                                  padding:
                                                                    '4px 8px',
                                                                  textAlign:
                                                                    'right',
                                                                  whiteSpace:
                                                                    'nowrap',
                                                                }}
                                                              >
                                                                $
                                                                {totalValue.toFixed(
                                                                  2
                                                                )}
                                                              </td>
                                                            </tr>
                                                          );
                                                        }
                                                      )}
                                                    </tbody>
                                                  </table>
                                                )}
                                              </div>
                                            );
                                          }
                                        )}
                                      </div>
                                    );
                                  })()
                                ) : (
                                  description || 'Block placeholder'
                                )}
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
                                  onClick={() => {
                                    const desc = (block.payload && typeof block.payload.description === 'string') 
                                      ? block.payload.description 
                                      : block.type;
                                    setConversationContext({
                                      type: 'quote',
                                      id: quote.id.toString(),
                                      version: quote.version.toString(),
                                      subject: `Block: ${desc}`,
                                      subjectKey: `quote_line:${block.id}`
                                    });
                                  }}
                                  title="Discuss Line Item"
                                >
                                  💬
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
                                    <div style={{ marginBottom: '10px' }}>
                                      <div style={{ marginBottom: '10px' }}>
                                        <div>Catalog Service</div>
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
                                                (c) =>
                                                  c.id === id &&
                                                  c.type === 'service'
                                              );
                                              if (item) {
                                                updateBlockDraft(
                                                  block.id,
                                                  'description',
                                                  item.name
                                                );
                                                updateBlockDraft(
                                                  block.id,
                                                  'sellValue',
                                                  item.unit_price
                                                );
                                              }
                                            }
                                          }}
                                          style={{ width: '100%' }}
                                        >
                                          <option value="">
                                            Select from services catalog
                                          </option>
                                          {catalogItems
                                            .filter(
                                              (item) => item.type === 'service'
                                            )
                                            .map((item) => (
                                              <option
                                                key={item.id}
                                                value={item.id}
                                              >
                                                {item.name}
                                              </option>
                                            ))}
                                        </select>
                                      </div>
                                      <div
                                        style={{
                                          display: 'grid',
                                          gridTemplateColumns:
                                            '2fr 1fr 1fr 1fr',
                                          gap: '10px',
                                        }}
                                      >
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
                                          <div>Quantity</div>
                                          <input
                                            type="number"
                                            min={0}
                                            value={draft.quantity ?? 1}
                                            onChange={(e) =>
                                              updateBlockDraft(
                                                block.id,
                                                'quantity',
                                                e.target.value
                                              )
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
                                              updateBlockDraft(
                                                block.id,
                                                'sellValue',
                                                e.target.value
                                              )
                                            }
                                            style={{ width: '100%' }}
                                          />
                                        </div>
                                        <div>
                                          <div>Owner/Team</div>
                                          <select
                                            value={
                                              draft.ownerType === 'employee' &&
                                              typeof draft.ownerId === 'number'
                                                ? `employee:${draft.ownerId}`
                                                : draft.ownerType === 'team' &&
                                                  typeof draft.teamId ===
                                                    'number'
                                                ? `team:${draft.teamId}`
                                                : ''
                                            }
                                            onChange={(e) => {
                                              const value = e.target.value;
                                              if (!value) {
                                                updateBlockDraft(
                                                  block.id,
                                                  'ownerType',
                                                  ''
                                                );
                                                updateBlockDraft(
                                                  block.id,
                                                  'ownerId',
                                                  null
                                                );
                                                updateBlockDraft(
                                                  block.id,
                                                  'teamId',
                                                  null
                                                );
                                                updateBlockDraft(
                                                  block.id,
                                                  'owner',
                                                  ''
                                                );
                                                updateBlockDraft(
                                                  block.id,
                                                  'team',
                                                  ''
                                                );
                                                return;
                                              }
                                              const [kind, idStr] =
                                                value.split(':');
                                              const id = Number(idStr);
                                              if (kind === 'employee') {
                                                const employee =
                                                  employees.find(
                                                    (emp) =>
                                                      emp.id === id
                                                  );
                                                updateBlockDraft(
                                                  block.id,
                                                  'ownerType',
                                                  'employee'
                                                );
                                                updateBlockDraft(
                                                  block.id,
                                                  'ownerId',
                                                  id
                                                );
                                                updateBlockDraft(
                                                  block.id,
                                                  'teamId',
                                                  null
                                                );
                                                updateBlockDraft(
                                                  block.id,
                                                  'team',
                                                  ''
                                                );
                                                updateBlockDraft(
                                                  block.id,
                                                  'owner',
                                                  employee
                                                    ? `${employee.firstName} ${employee.lastName}`
                                                    : ''
                                                );
                                              } else if (kind === 'team') {
                                                const team = teams.find(
                                                  (t) => t.id === id
                                                );
                                                updateBlockDraft(
                                                  block.id,
                                                  'ownerType',
                                                  'team'
                                                );
                                                updateBlockDraft(
                                                  block.id,
                                                  'teamId',
                                                  id
                                                );
                                                updateBlockDraft(
                                                  block.id,
                                                  'ownerId',
                                                  null
                                                );
                                                updateBlockDraft(
                                                  block.id,
                                                  'owner',
                                                  ''
                                                );
                                                updateBlockDraft(
                                                  block.id,
                                                  'team',
                                                  team ? team.name : ''
                                                );
                                              }
                                            }}
                                            style={{ width: '100%' }}
                                          >
                                            <option value="">
                                              Select owner or team
                                            </option>
                                            {teams
                                              .filter(
                                                (t) => t.status === 'active'
                                              )
                                              .slice()
                                              .sort((a, b) =>
                                                a.name.localeCompare(b.name)
                                              )
                                              .map((t) => (
                                                <option
                                                  key={`team-${t.id}`}
                                                  value={`team:${t.id}`}
                                                >
                                                  {t.name}
                                                </option>
                                              ))}
                                            <option value="" disabled>
                                              ────────────────
                                            </option>
                                            {employees
                                              .filter(
                                                (e) =>
                                                  e.status !== 'archived'
                                              )
                                              .slice()
                                              .sort((a, b) => {
                                                const aName = `${a.firstName} ${a.lastName}`.toLowerCase();
                                                const bName = `${b.firstName} ${b.lastName}`.toLowerCase();
                                                return aName.localeCompare(
                                                  bName
                                                );
                                              })
                                              .map((e) => (
                                                <option
                                                  key={`employee-${e.id}`}
                                                  value={`employee:${e.id}`}
                                                >
                                                  {e.firstName} {e.lastName}
                                                </option>
                                              ))}
                                          </select>
                                        </div>
                                      </div>
                                    </div>
                                  )}

                                  {block.type === 'OnceOffProjectBlock' && (
                                    <div style={{ marginBottom: '10px' }}>
                                      <div style={{ marginBottom: '10px' }}>
                                        <div>Project Description</div>
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
                                      <div
                                        style={{
                                          display: 'flex',
                                          justifyContent: 'space-between',
                                          alignItems: 'center',
                                          marginBottom: '6px',
                                        }}
                                      >
                                        <strong>Phases</strong>
                                        <button
                                          type="button"
                                          className="button button-secondary"
                                          onClick={() => addProjectPhase(block.id)}
                                        >
                                          Add Phase
                                        </button>
                                      </div>
                                      <div>
                                        {(draft.phases || []).map(
                                          (phase: any, phaseIndex: number) => {
                                            const units = Array.isArray(
                                              phase.units
                                            )
                                              ? phase.units
                                              : [];
                                            const phaseTotal = units.reduce(
                                              (sum: number, u: any) =>
                                                sum +
                                                (Number.isFinite(
                                                  Number(u.totalValue)
                                                )
                                                  ? Number(u.totalValue)
                                                  : 0),
                                              0
                                            );

                                            return (
                                              <div
                                                key={phase.id || phaseIndex}
                                                data-test="project-phase-panel"
                                                style={{
                                                  border:
                                                    '1px solid #ccd0d4',
                                                  padding: '10px',
                                                  marginBottom: '8px',
                                                  background: '#fff',
                                                }}
                                              >
                                                <div
                                                  style={{
                                                    display: 'flex',
                                                    justifyContent:
                                                      'space-between',
                                                    alignItems: 'center',
                                                    marginBottom: '6px',
                                                  }}
                                                >
                                                  <input
                                                    type="text"
                                                    value={
                                                      phase.name || ''
                                                    }
                                                    onChange={(e) =>
                                                      updateProjectDraft(
                                                        block.id,
                                                        (d) => {
                                                          const phases =
                                                            Array.isArray(
                                                              d.phases
                                                            )
                                                              ? [
                                                                  ...d.phases,
                                                                ]
                                                              : [];
                                                          if (
                                                            phaseIndex >=
                                                            0 &&
                                                            phaseIndex <
                                                              phases.length
                                                          ) {
                                                            phases[
                                                              phaseIndex
                                                            ] = {
                                                              ...(phases[
                                                                phaseIndex
                                                              ] || {}),
                                                              name:
                                                                e.target
                                                                  .value,
                                                            };
                                                          }
                                                          d.phases =
                                                            phases;
                                                          return d;
                                                        }
                                                      )
                                                    }
                                                    placeholder="Phase name"
                                                    style={{
                                                      flex: 1,
                                                      marginRight: '8px',
                                                    }}
                                                  />
                                                  <div>
                                                    <button
                                                      type="button"
                                                      className="button button-small"
                                                      onClick={() =>
                                                        moveProjectPhase(
                                                          block.id,
                                                          phaseIndex,
                                                          -1
                                                        )
                                                      }
                                                    >
                                                      ↑
                                                    </button>
                                                    <button
                                                      type="button"
                                                      className="button button-small"
                                                      style={{
                                                        marginLeft: '4px',
                                                      }}
                                                      onClick={() =>
                                                        moveProjectPhase(
                                                          block.id,
                                                          phaseIndex,
                                                          1
                                                        )
                                                      }
                                                    >
                                                      ↓
                                                    </button>
                                                    <button
                                                      type="button"
                                                      className="button button-small"
                                                      style={{
                                                        marginLeft: '4px',
                                                      }}
                                                      onClick={() =>
                                                        removeProjectPhase(
                                                          block.id,
                                                          phaseIndex
                                                        )
                                                      }
                                                    >
                                                      Delete
                                                    </button>
                                                  </div>
                                                </div>
                                                <table
                                                  className="widefat fixed striped"
                                                  data-test="project-phase-units"
                                                  style={{
                                                    marginTop: '4px',
                                                  }}
                                                >
                                                  <thead>
                                                    <tr>
                                                      <th>Description</th>
                                                      <th
                                                        style={{
                                                          textAlign:
                                                            'right',
                                                        }}
                                                      >
                                                        Qty
                                                      </th>
                                                      <th
                                                        style={{
                                                          textAlign:
                                                            'right',
                                                        }}
                                                      >
                                                        Unit Price
                                                      </th>
                                                      <th
                                                        style={{
                                                          textAlign:
                                                            'right',
                                                        }}
                                                      >
                                                        Total
                                                      </th>
                                                      <th
                                                        style={{
                                                          textAlign:
                                                            'right',
                                                        }}
                                                      >
                                                        Actions
                                                      </th>
                                                    </tr>
                                                  </thead>
                                                  <tbody>
                                                    {units.map(
                                                      (
                                                        unit: any,
                                                        unitIndex: number
                                                      ) => (
                                                        <tr
                                                          key={
                                                            unit.id ||
                                                            unitIndex
                                                          }
                                                        >
                                                          <td>
                                                            <div
                                                              style={{
                                                                border:
                                                                  '1px solid #ccd0d4',
                                                                background:
                                                                  '#fdfdfd',
                                                                padding: '6px',
                                                                borderRadius:
                                                                  '2px',
                                                                display:
                                                                  'flex',
                                                                flexDirection:
                                                                  'column',
                                                                gap: '6px',
                                                              }}
                                                            >
                                                              <div
                                                                style={{
                                                                  display:
                                                                    'grid',
                                                                  gridTemplateColumns:
                                                                    '1fr 1fr',
                                                                  gap: '4px',
                                                                }}
                                                              >
                                                              <div>
                                                                <div
                                                                  style={{
                                                                    fontSize:
                                                                      '11px',
                                                                    color:
                                                                      '#555',
                                                                  }}
                                                                >
                                                                  Catalog
                                                                  Service
                                                                </div>
                                                                <select
                                                                  value={
                                                                    unit.catalogItemId ??
                                                                    ''
                                                                  }
                                                                  onChange={(
                                                                    e
                                                                  ) => {
                                                                    const value =
                                                                      e.target
                                                                        .value;
                                                                    const id =
                                                                      value
                                                                        ? Number(
                                                                            value
                                                                          )
                                                                        : null;
                                                                    updateProjectDraft(
                                                                      block.id,
                                                                      (
                                                                        draft
                                                                      ) => {
                                                                        const phases =
                                                                          Array.isArray(
                                                                            draft.phases
                                                                          )
                                                                            ? [
                                                                                ...draft.phases,
                                                                              ]
                                                                            : [];
                                                                        if (
                                                                          phaseIndex <
                                                                            0 ||
                                                                          phaseIndex >=
                                                                            phases.length
                                                                        ) {
                                                                          return draft;
                                                                        }
                                                                        const phase =
                                                                          {
                                                                            ...phases[
                                                                              phaseIndex
                                                                            ],
                                                                          };
                                                                        const unitsInner =
                                                                          Array.isArray(
                                                                            phase.units
                                                                          )
                                                                            ? [
                                                                                ...phase.units,
                                                                              ]
                                                                            : [];
                                                                        if (
                                                                          unitIndex <
                                                                            0 ||
                                                                          unitIndex >=
                                                                            unitsInner.length
                                                                        ) {
                                                                          return draft;
                                                                        }
                                                                        const unitInner =
                                                                          {
                                                                            ...unitsInner[
                                                                              unitIndex
                                                                            ],
                                                                          };
                                                                        unitInner.catalogItemId =
                                                                          id;
                                                                        if (
                                                                          id !==
                                                                          null
                                                                        ) {
                                                                          const item =
                                                                            catalogItems.find(
                                                                              (
                                                                                c
                                                                              ) =>
                                                                                c.id ===
                                                                                  id &&
                                                                                c.type ===
                                                                                  'service'
                                                                            );
                                                                          if (
                                                                            item
                                                                          ) {
                                                                            unitInner.description =
                                                                              item.name;
                                                                            unitInner.unitPrice =
                                                                              item.unit_price;
                                                                            const quantityInner =
                                                                              Number.isFinite(
                                                                                Number(
                                                                                  unitInner.quantity
                                                                                )
                                                                              )
                                                                                ? Number(
                                                                                    unitInner.quantity
                                                                                  )
                                                                                : 0;
                                                                            unitInner.totalValue =
                                                                              quantityInner *
                                                                              item.unit_price;
                                                                          }
                                                                        }
                                                                        unitsInner[
                                                                          unitIndex
                                                                        ] =
                                                                          unitInner;
                                                                        phase.units =
                                                                          unitsInner;
                                                                        phase.phaseTotalValue =
                                                                          unitsInner.reduce(
                                                                            (
                                                                              sum: number,
                                                                              u: any
                                                                            ) =>
                                                                              sum +
                                                                              (u.totalValue ||
                                                                                0),
                                                                            0
                                                                          );
                                                                        phases[
                                                                          phaseIndex
                                                                        ] =
                                                                          phase;
                                                                        draft.phases =
                                                                          phases;
                                                                        return draft;
                                                                      }
                                                                    );
                                                                  }}
                                                                  style={{
                                                                    width:
                                                                      '100%',
                                                                  }}
                                                                >
                                                                  <option value="">
                                                                    Select
                                                                    service
                                                                  </option>
                                                                  {catalogItems
                                                                    .filter(
                                                                      (
                                                                        item
                                                                      ) =>
                                                                        item.type ===
                                                                        'service'
                                                                    )
                                                                    .map(
                                                                      (
                                                                        item
                                                                      ) => (
                                                                        <option
                                                                          key={
                                                                            item.id
                                                                          }
                                                                          value={
                                                                            item.id
                                                                          }
                                                                        >
                                                                          {
                                                                            item.name
                                                                          }
                                                                        </option>
                                                                      )
                                                                    )}
                                                                </select>
                                                              </div>
                                                            </div>
                                                            <div>
                                                                <div
                                                                  style={{
                                                                    fontSize:
                                                                      '11px',
                                                                    color:
                                                                      '#555',
                                                                  }}
                                                                >
                                                                  Owner/Team
                                                                </div>
                                                                <select
                                                                  value={
                                                                    unit.ownerType ===
                                                                      'employee' &&
                                                                    typeof unit.ownerId ===
                                                                      'number'
                                                                      ? `employee:${unit.ownerId}`
                                                                      : unit.ownerType ===
                                                                          'team' &&
                                                                        typeof unit.teamId ===
                                                                          'number'
                                                                      ? `team:${unit.teamId}`
                                                                      : ''
                                                                  }
                                                                  onChange={(
                                                                    e
                                                                  ) => {
                                                                    const value =
                                                                      e.target
                                                                        .value;
                                                                    updateProjectDraft(
                                                                      block.id,
                                                                      (
                                                                        draft
                                                                      ) => {
                                                                        const phases =
                                                                          Array.isArray(
                                                                            draft.phases
                                                                          )
                                                                            ? [
                                                                                ...draft.phases,
                                                                              ]
                                                                            : [];
                                                                        if (
                                                                          phaseIndex <
                                                                            0 ||
                                                                          phaseIndex >=
                                                                            phases.length
                                                                        ) {
                                                                          return draft;
                                                                        }
                                                                        const phase =
                                                                          {
                                                                            ...phases[
                                                                              phaseIndex
                                                                            ],
                                                                          };
                                                                        const unitsInner =
                                                                          Array.isArray(
                                                                            phase.units
                                                                          )
                                                                            ? [
                                                                                ...phase.units,
                                                                              ]
                                                                            : [];
                                                                        if (
                                                                          unitIndex <
                                                                            0 ||
                                                                          unitIndex >=
                                                                            unitsInner.length
                                                                        ) {
                                                                          return draft;
                                                                        }
                                                                        const unitInner =
                                                                          {
                                                                            ...unitsInner[
                                                                              unitIndex
                                                                            ],
                                                                          };

                                                                        if (
                                                                          !value
                                                                        ) {
                                                                          unitInner.ownerType =
                                                                            '';
                                                                          unitInner.ownerId =
                                                                            null;
                                                                          unitInner.teamId =
                                                                            null;
                                                                          unitInner.owner =
                                                                            '';
                                                                          unitInner.team =
                                                                            '';
                                                                        } else {
                                                                          const [
                                                                            kind,
                                                                            idStr,
                                                                          ] =
                                                                            value.split(
                                                                              ':'
                                                                            );
                                                                          const id =
                                                                            Number(
                                                                              idStr
                                                                            );
                                                                          if (
                                                                            kind ===
                                                                            'employee'
                                                                          ) {
                                                                            const employee =
                                                                              employees.find(
                                                                                (
                                                                                  emp
                                                                                ) =>
                                                                                  emp.id ===
                                                                                  id
                                                                              );
                                                                            unitInner.ownerType =
                                                                              'employee';
                                                                            unitInner.ownerId =
                                                                              id;
                                                                            unitInner.teamId =
                                                                              null;
                                                                            unitInner.team =
                                                                              '';
                                                                            unitInner.owner =
                                                                              employee
                                                                                ? `${employee.firstName} ${employee.lastName}`
                                                                                : '';
                                                                          } else if (
                                                                            kind ===
                                                                            'team'
                                                                          ) {
                                                                            const team =
                                                                              teams.find(
                                                                                (
                                                                                  t
                                                                                ) =>
                                                                                  t.id ===
                                                                                  id
                                                                              );
                                                                            unitInner.ownerType =
                                                                              'team';
                                                                            unitInner.teamId =
                                                                              id;
                                                                            unitInner.ownerId =
                                                                              null;
                                                                            unitInner.owner =
                                                                              '';
                                                                            unitInner.team =
                                                                              team
                                                                                ? team.name
                                                                                : '';
                                                                          }
                                                                        }

                                                                        unitsInner[
                                                                          unitIndex
                                                                        ] =
                                                                          unitInner;
                                                                        phase.units =
                                                                          unitsInner;
                                                                        phase.phaseTotalValue =
                                                                          unitsInner.reduce(
                                                                            (
                                                                              sum: number,
                                                                              u: any
                                                                            ) =>
                                                                              sum +
                                                                              (u.totalValue ||
                                                                                0),
                                                                            0
                                                                          );
                                                                        phases[
                                                                          phaseIndex
                                                                        ] =
                                                                          phase;
                                                                        draft.phases =
                                                                          phases;
                                                                        return draft;
                                                                      }
                                                                    );
                                                                  }}
                                                                  style={{
                                                                    width:
                                                                      '100%',
                                                                  }}
                                                                >
                                                                  <option value="">
                                                                    Select
                                                                    owner or
                                                                    team
                                                                  </option>
                                                                  {teams
                                                                    .filter(
                                                                      (t) =>
                                                                        t.status ===
                                                                        'active'
                                                                    )
                                                                    .slice()
                                                                    .sort(
                                                                      (
                                                                        a,
                                                                        b
                                                                      ) =>
                                                                        a.name.localeCompare(
                                                                          b.name
                                                                        )
                                                                    )
                                                                    .map(
                                                                      (t) => (
                                                                        <option
                                                                          key={`team-${t.id}`}
                                                                          value={`team:${t.id}`}
                                                                        >
                                                                          {t.name}
                                                                        </option>
                                                                      )
                                                                    )}
                                                                  <option
                                                                    value=""
                                                                    disabled
                                                                  >
                                                                    ────────────────
                                                                  </option>
                                                                  {employees
                                                                    .filter(
                                                                      (e) =>
                                                                        e.status !==
                                                                        'archived'
                                                                    )
                                                                    .slice()
                                                                    .sort(
                                                                      (
                                                                        a,
                                                                        b
                                                                      ) => {
                                                                        const aName = `${a.firstName} ${a.lastName}`.toLowerCase();
                                                                        const bName = `${b.firstName} ${b.lastName}`.toLowerCase();
                                                                        return aName.localeCompare(
                                                                          bName
                                                                        );
                                                                      }
                                                                    )
                                                                    .map(
                                                                      (e) => (
                                                                        <option
                                                                          key={`employee-${e.id}`}
                                                                          value={`employee:${e.id}`}
                                                                        >
                                                                          {
                                                                            e.firstName
                                                                          }{' '}
                                                                          {
                                                                            e.lastName
                                                                          }
                                                                        </option>
                                                                      )
                                                                    )}
                                                                </select>
                                                              </div>
                                                              <div>
                                                                <div
                                                                  style={{
                                                                    fontSize:
                                                                      '11px',
                                                                    color:
                                                                      '#555',
                                                                    marginBottom:
                                                                      '2px',
                                                                  }}
                                                                >
                                                                  Description
                                                                </div>
                                                                <input
                                                                  type="text"
                                                                  value={
                                                                    unit.description ||
                                                                    ''
                                                                  }
                                                                  onChange={(
                                                                    e
                                                                  ) =>
                                                                    updateProjectUnitField(
                                                                      block.id,
                                                                      phaseIndex,
                                                                      unitIndex,
                                                                      'description'
                                                                    )(
                                                                      e.target
                                                                        .value
                                                                    )
                                                                  }
                                                                  style={{
                                                                    width:
                                                                      '100%',
                                                                  }}
                                                                />
                                                              </div>
                                                            </div>
                                                          </td>
                                                          <td
                                                            style={{
                                                              textAlign:
                                                                'right',
                                                            }}
                                                          >
                                                            <input
                                                              type="number"
                                                              min={0}
                                                              value={
                                                                unit.quantity ??
                                                                0
                                                              }
                                                              onChange={(
                                                                e
                                                              ) =>
                                                                updateProjectUnitField(
                                                                  block.id,
                                                                  phaseIndex,
                                                                  unitIndex,
                                                                  'quantity'
                                                                )(
                                                                  e
                                                                    .target
                                                                    .value
                                                                )
                                                              }
                                                              style={{
                                                                width:
                                                                  '80px',
                                                              }}
                                                            />
                                                          </td>
                                                          <td
                                                            style={{
                                                              textAlign:
                                                                'right',
                                                            }}
                                                          >
                                                            <input
                                                              type="number"
                                                              min={0}
                                                              value={
                                                                unit.unitPrice ??
                                                                0
                                                              }
                                                              onChange={(
                                                                e
                                                              ) =>
                                                                updateProjectUnitField(
                                                                  block.id,
                                                                  phaseIndex,
                                                                  unitIndex,
                                                                  'unitPrice'
                                                                )(
                                                                  e
                                                                    .target
                                                                    .value
                                                                )
                                                              }
                                                              style={{
                                                                width:
                                                                  '100px',
                                                              }}
                                                            />
                                                          </td>
                                                          <td
                                                            style={{
                                                              textAlign:
                                                                'right',
                                                            }}
                                                          >
                                                            $
                                                            {(
                                                              unit.totalValue ??
                                                              0
                                                            ).toFixed(2)}
                                                          </td>
                                                          <td
                                                            style={{
                                                              textAlign:
                                                                'right',
                                                            }}
                                                          >
                                                            <button
                                                              type="button"
                                                              className="button button-small"
                                                              onClick={() =>
                                                                moveProjectUnit(
                                                                  block.id,
                                                                  phaseIndex,
                                                                  unitIndex,
                                                                  -1
                                                                )
                                                              }
                                                            >
                                                              ↑
                                                            </button>
                                                            <button
                                                              type="button"
                                                              className="button button-small"
                                                              style={{
                                                                marginLeft:
                                                                  '4px',
                                                              }}
                                                              onClick={() =>
                                                                moveProjectUnit(
                                                                  block.id,
                                                                  phaseIndex,
                                                                  unitIndex,
                                                                  1
                                                                )
                                                              }
                                                            >
                                                              ↓
                                                            </button>
                                                            <button
                                                              type="button"
                                                              className="button button-small"
                                                              style={{
                                                                marginLeft:
                                                                  '4px',
                                                              }}
                                                              onClick={() =>
                                                                removeProjectUnit(
                                                                  block.id,
                                                                  phaseIndex,
                                                                  unitIndex
                                                                )
                                                              }
                                                            >
                                                              Delete
                                                            </button>
                                                          </td>
                                                        </tr>
                                                      )
                                                    )}
                                                    {units.length ===
                                                      0 && (
                                                      <tr>
                                                        <td
                                                          colSpan={5}
                                                          style={{
                                                            fontStyle:
                                                              'italic',
                                                          }}
                                                        >
                                                          No units yet.
                                                        </td>
                                                      </tr>
                                                    )}
                                                  </tbody>
                                                </table>
                                                <div
                                                  style={{
                                                    marginTop: '6px',
                                                    display: 'flex',
                                                    justifyContent:
                                                      'space-between',
                                                    alignItems: 'center',
                                                  }}
                                                >
                                                  <button
                                                    type="button"
                                                    className="button button-secondary"
                                                    onClick={() =>
                                                      addProjectUnit(
                                                        block.id,
                                                        phaseIndex
                                                      )
                                                    }
                                                  >
                                                    Add Unit
                                                  </button>
                                                  <span>
                                                    Phase Total:{' '}
                                                    $
                                                    {phaseTotal.toFixed(
                                                      2
                                                    )}
                                                  </span>
                                                </div>
                                              </div>
                                            );
                                          }
                                        )}
                                        {(draft.phases || []).length ===
                                          0 && (
                                          <p
                                            style={{
                                              fontStyle: 'italic',
                                              color: '#666',
                                            }}
                                          >
                                            No phases defined yet.
                                          </p>
                                        )}
                                      </div>
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
                                        <div>Catalog Product</div>
                                        <select
                                          value={
                                            draft.catalogItemId ?? ''
                                          }
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
                                                (c) =>
                                                  c.id === id &&
                                                  c.type === 'product'
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
                                            Select from product catalog
                                          </option>
                                          {catalogItems
                                            .filter(
                                              (item) => item.type === 'product'
                                            )
                                            .map((item) => (
                                              <option
                                                key={item.id}
                                                value={item.id}
                                              >
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
                                            const qty = Number(
                                              draft.quantity ?? 1
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

                                  <div
                                    style={{
                                      marginTop: '10px',
                                      padding: '10px',
                                      background: '#fff',
                                      border: '1px solid #ccd0d4',
                                      display: 'flex',
                                      justifyContent: 'space-between',
                                      alignItems: 'center',
                                    }}
                                  >
                                    <div>
                                      <strong>Commercial Summary:</strong>{' '}
                                      {block.type === 'OnceOffSimpleServiceBlock' &&
                                        (() => {
                                          const qty = Number.isFinite(
                                            Number(draft.quantity)
                                          )
                                            ? Number(draft.quantity)
                                            : 1;
                                          const sell = Number.isFinite(
                                            Number(draft.sellValue)
                                          )
                                            ? Number(draft.sellValue)
                                            : 0;
                                          return `Qty ${qty} @ $${sell.toFixed(
                                            2
                                          )}`;
                                        })()}
                                      {block.type === 'OnceOffProjectBlock' && (() => {
                                        const phases = Array.isArray(draft.phases)
                                          ? draft.phases
                                          : [];
                                        const unitsCount = phases.reduce(
                                          (sum: number, phase: any) =>
                                            sum +
                                            (Array.isArray(phase.units)
                                              ? phase.units.length
                                              : 0),
                                          0
                                        );
                                        const totalValue = phases.reduce(
                                          (sum: number, phase: any) =>
                                            sum +
                                            (Number.isFinite(
                                              Number(phase.phaseTotalValue)
                                            )
                                              ? Number(phase.phaseTotalValue)
                                              : 0),
                                          0
                                        );
                                        return `Total Value $${totalValue.toFixed(
                                          2
                                        )} (${phases.length} phases / ${unitsCount} units)`;
                                      })()}
                                      {block.type === 'PriceAdjustmentBlock' &&
                                        (() => {
                                          const amount = Number.isFinite(
                                            Number(draft.amount)
                                          )
                                            ? Number(draft.amount)
                                            : 0;
                                          return `Adjustment $${amount.toFixed(
                                            2
                                          )}`;
                                        })()}
                                      {block.type === 'TextBlock' &&
                                        (draft.text || '')}
                                    </div>
                                    <div>
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
                                        onClick={() => cancelBlockEdit(block.id)}
                                        disabled={savingBlockId === block.id}
                                      >
                                        Cancel
                                      </button>
                                    </div>
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
                  <p
                    style={{
                      fontStyle: 'italic',
                      color: '#666',
                      margin: '10px 0',
                    }}
                  >
                    No blocks in this section yet.
                  </p>
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
        <p style={{ fontStyle: 'italic', color: '#666' }}>No sections defined yet.</p>
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
                                padding: '10px',
                                background: '#fff',
                                border: '1px solid #ccd0d4',
                                display: 'flex',
                                justifyContent: 'space-between',
                                alignItems: 'center',
                              }}
                            >
                              <div>
                                <strong>Commercial Summary:</strong>{' '}
                                {block.type === 'OnceOffSimpleServiceBlock' &&
                                  (() => {
                                    const qty = Number.isFinite(
                                      Number(draft.quantity)
                                    )
                                      ? Number(draft.quantity)
                                      : 1;
                                    const sell = Number.isFinite(
                                      Number(draft.sellValue)
                                    )
                                      ? Number(draft.sellValue)
                                      : 0;
                                    return `Qty ${qty} @ $${sell.toFixed(2)}`;
                                  })()}
                                {block.type === 'OnceOffProjectBlock' &&
                                  (() => {
                                    const total = Number.isFinite(
                                      Number(draft.totalValue)
                                    )
                                      ? Number(draft.totalValue)
                                      : 0;
                                    return `Total Value $${total.toFixed(2)}`;
                                  })()}
                                {block.type === 'HardwareBlock' &&
                                  (() => {
                                    const qty = Number.isFinite(
                                      Number(draft.quantity)
                                    )
                                      ? Number(draft.quantity)
                                      : 1;
                                    const unit = Number.isFinite(
                                      Number(draft.unitPrice)
                                    )
                                      ? Number(draft.unitPrice)
                                      : 0;
                                    const total = Number.isFinite(
                                      Number(draft.totalValue)
                                    )
                                      ? Number(draft.totalValue)
                                      : 0;
                                    return `Qty ${qty} @ $${unit.toFixed(
                                      2
                                    )} = $${total.toFixed(2)}`;
                                  })()}
                                {block.type === 'PriceAdjustmentBlock' &&
                                  (() => {
                                    const amount = Number.isFinite(
                                      Number(draft.amount)
                                    )
                                      ? Number(draft.amount)
                                      : 0;
                                    return `Adjustment $${amount.toFixed(2)}`;
                                  })()}
                                {block.type === 'TextBlock' &&
                                  (draft.text || '').slice(0, 40)}
                              </div>
                              <div>
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
                                  onClick={() => cancelBlockEdit(block.id)}
                                  disabled={savingBlockId === block.id}
                                >
                                  Cancel
                                </button>
                              </div>
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
                      } catch (err) {
                        alert(
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

      {conversationContext && (
        <div className="pet-modal-overlay" style={{
          position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
          backgroundColor: 'rgba(0,0,0,0.5)', display: 'flex', justifyContent: 'center', alignItems: 'center',
          zIndex: 1000
        }}>
          <div className="pet-modal-content" style={{
            background: 'white', padding: '20px', borderRadius: '5px', width: '800px', maxWidth: '90%', maxHeight: '90vh', overflowY: 'auto',
            boxShadow: '0 2px 10px rgba(0,0,0,0.1)', display: 'flex', flexDirection: 'column'
          }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px' }}>
              <h3 style={{ margin: 0 }}>Conversation: {conversationContext.subject}</h3>
              <button onClick={() => setConversationContext(null)} style={{ border: 'none', background: 'none', cursor: 'pointer', fontSize: '1.2em' }}>&times;</button>
            </div>
            <ConversationPanel
              contextType={conversationContext.type}
              contextId={conversationContext.id}
              contextVersion={conversationContext.version}
              defaultSubject={conversationContext.subject}
              subjectKey={conversationContext.subjectKey}
            />
          </div>
        </div>
      )}
    </div>
  );
};

export default QuoteDetails;
