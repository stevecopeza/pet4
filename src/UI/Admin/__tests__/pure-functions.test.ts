import { describe, it, expect } from 'vitest';
import { validateBlockDraft } from '../components/InlineValidation';
import { computeProjectSummary } from '../components/ProjectBlockEditor';
import { computeQuoteTotals, getBaseBlockValue } from '../utils/quoteTotals';
import type { Quote, QuoteBlock, QuoteSection } from '../types';

// ---------------------------------------------------------------------------
// validateBlockDraft
// ---------------------------------------------------------------------------
describe('validateBlockDraft', () => {
  it('returns no errors for TextBlock', () => {
    expect(validateBlockDraft({}, 'TextBlock')).toEqual({});
  });

  it('requires description for PriceAdjustmentBlock', () => {
    const errors = validateBlockDraft({ description: '' }, 'PriceAdjustmentBlock');
    expect(errors.description).toBeDefined();
  });

  it('passes valid PriceAdjustmentBlock', () => {
    const errors = validateBlockDraft({ description: 'Discount' }, 'PriceAdjustmentBlock');
    expect(Object.keys(errors)).toHaveLength(0);
  });

  it('requires description for service block', () => {
    const errors = validateBlockDraft(
      { description: '', quantity: 1, sellValue: 100 },
      'OnceOffSimpleServiceBlock'
    );
    expect(errors.description).toBeDefined();
    expect(errors.quantity).toBeUndefined();
  });

  it('requires quantity >= 1 for service block', () => {
    const errors = validateBlockDraft(
      { description: 'Setup', quantity: 0, sellValue: 100 },
      'OnceOffSimpleServiceBlock'
    );
    expect(errors.quantity).toBeDefined();
  });

  it('rejects negative price for service block', () => {
    const errors = validateBlockDraft(
      { description: 'Setup', quantity: 1, sellValue: -5 },
      'OnceOffSimpleServiceBlock'
    );
    expect(errors.price).toBeDefined();
  });

  it('accepts valid service block draft', () => {
    const errors = validateBlockDraft(
      { description: 'Setup', quantity: 2, sellValue: 50 },
      'OnceOffSimpleServiceBlock'
    );
    expect(Object.keys(errors)).toHaveLength(0);
  });

  it('skips price check for project block', () => {
    const errors = validateBlockDraft(
      { description: 'Project A', quantity: 1 },
      'OnceOffProjectBlock'
    );
    expect(errors.price).toBeUndefined();
  });
});

// ---------------------------------------------------------------------------
// computeProjectSummary
// ---------------------------------------------------------------------------
describe('computeProjectSummary', () => {
  it('returns "0 phases · 0 units" for empty payload', () => {
    expect(computeProjectSummary({})).toBe('0 phases · 0 units');
  });

  it('counts phases and units', () => {
    const payload = {
      phases: [
        { units: [{ quantity: 5, unit: 'hours' }, { quantity: 3, unit: 'hours' }] },
        { units: [{ quantity: 10, unit: 'days' }] },
      ],
    };
    expect(computeProjectSummary(payload)).toBe('2 phases · 3 units · 8h');
  });

  it('uses singular "phase" for 1 phase', () => {
    const payload = { phases: [{ units: [] }] };
    expect(computeProjectSummary(payload)).toBe('1 phase · 0 units');
  });

  it('omits hours when no hour-unit items exist', () => {
    const payload = {
      phases: [{ units: [{ quantity: 2, unit: 'days' }] }],
    };
    expect(computeProjectSummary(payload)).toBe('1 phase · 1 unit');
  });

  it('treats units with no unit field as hours', () => {
    const payload = {
      phases: [{ units: [{ quantity: 4 }] }],
    };
    expect(computeProjectSummary(payload)).toBe('1 phase · 1 unit · 4h');
  });
});

// ---------------------------------------------------------------------------
// getBaseBlockValue
// ---------------------------------------------------------------------------
describe('getBaseBlockValue', () => {
  const makeBlock = (overrides: Partial<QuoteBlock>): QuoteBlock => ({
    id: 1,
    quoteId: 1,
    sectionId: 1,
    type: 'OnceOffSimpleServiceBlock',
    orderIndex: 0,
    componentId: null,
    priced: true,
    payload: {},
    ...overrides,
  });

  it('returns 0 for non-priced blocks', () => {
    expect(getBaseBlockValue(makeBlock({ priced: false }))).toBe(0);
  });

  it('reads totalValue first', () => {
    expect(
      getBaseBlockValue(makeBlock({ payload: { totalValue: 500, sellValue: 100 } }))
    ).toBe(500);
  });

  it('falls back to sellValue', () => {
    expect(
      getBaseBlockValue(makeBlock({ payload: { sellValue: 250 } }))
    ).toBe(250);
  });

  it('returns 0 when no value fields exist', () => {
    expect(getBaseBlockValue(makeBlock({ payload: {} }))).toBe(0);
  });
});

// ---------------------------------------------------------------------------
// computeQuoteTotals
// ---------------------------------------------------------------------------
describe('computeQuoteTotals', () => {
  const makeQuote = (
    sections: QuoteSection[],
    blocks: QuoteBlock[]
  ): Quote =>
    ({
      id: 1,
      customerId: 1,
      title: 'Test',
      state: 'draft',
      version: 1,
      currency: 'USD',
      sections,
      blocks,
      components: [],
    } as unknown as Quote);

  it('returns zero for empty quote', () => {
    const result = computeQuoteTotals(makeQuote([], []));
    expect(result.quoteTotal).toBe(0);
    expect(result.sectionTotals).toEqual({});
  });

  it('sums section block values', () => {
    const sections: QuoteSection[] = [
      { id: 10, quoteId: 1, name: 'Dev', orderIndex: 0, showTotalValue: true, showItemCount: false, showTotalHours: false },
    ];
    const blocks: QuoteBlock[] = [
      { id: 1, quoteId: 1, sectionId: 10, type: 'OnceOffSimpleServiceBlock', orderIndex: 0, componentId: null, priced: true, payload: { totalValue: 200 } },
      { id: 2, quoteId: 1, sectionId: 10, type: 'OnceOffSimpleServiceBlock', orderIndex: 1, componentId: null, priced: true, payload: { sellValue: 300 } },
    ];
    const result = computeQuoteTotals(makeQuote(sections, blocks));
    expect(result.quoteTotal).toBe(500);
    expect(result.sectionTotals[10]).toBe(500);
  });

  it('includes section-scoped price adjustments in section total', () => {
    const sections: QuoteSection[] = [
      { id: 10, quoteId: 1, name: 'Dev', orderIndex: 0, showTotalValue: true, showItemCount: false, showTotalHours: false },
    ];
    const blocks: QuoteBlock[] = [
      { id: 1, quoteId: 1, sectionId: 10, type: 'OnceOffSimpleServiceBlock', orderIndex: 0, componentId: null, priced: true, payload: { totalValue: 1000 } },
      { id: 2, quoteId: 1, sectionId: 10, type: 'PriceAdjustmentBlock', orderIndex: 1, componentId: null, priced: false, payload: { amount: -100 } },
    ];
    const result = computeQuoteTotals(makeQuote(sections, blocks));
    expect(result.sectionTotals[10]).toBe(900);
    expect(result.quoteTotal).toBe(900);
  });

  it('treats adjustment-only section as quote-scoped', () => {
    const sections: QuoteSection[] = [
      { id: 10, quoteId: 1, name: 'Discount', orderIndex: 0, showTotalValue: true, showItemCount: false, showTotalHours: false },
    ];
    const blocks: QuoteBlock[] = [
      { id: 1, quoteId: 1, sectionId: 10, type: 'PriceAdjustmentBlock', orderIndex: 0, componentId: null, priced: false, payload: { amount: -50 } },
    ];
    const result = computeQuoteTotals(makeQuote(sections, blocks));
    // Section total only has base + section adjustments (no priced blocks → not section-scoped)
    expect(result.sectionTotals[10]).toBe(0);
    // But quote total includes it
    expect(result.quoteTotal).toBe(-50);
  });
});
