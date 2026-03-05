import { Quote, QuoteBlock, QuoteSection } from '../types';

export const generateLocalId = () =>
  `id_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`;

export const getBaseBlockValue = (block: QuoteBlock): number => {
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

export const computeQuoteTotals = (
  quote: Quote
): { quoteTotal: number; sectionTotals: Record<number, number> } => {
  const sections = (quote.sections || []).slice();
  const blocks = (quote.blocks || []).slice();

  const sectionTotals: Record<number, number> = {};
  let rootPricedTotal = 0;
  let quoteScopedAdjustmentsTotal = 0;

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

export const isQuoteLevelAdjustmentSection = (
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

export const sortSections = (
  sections: QuoteSection[],
  blocks: QuoteBlock[]
): QuoteSection[] => {
  return sections.slice().sort((a, b) => {
    const aIsAdjustment = isQuoteLevelAdjustmentSection(a, blocks);
    const bIsAdjustment = isQuoteLevelAdjustmentSection(b, blocks);

    if (aIsAdjustment !== bIsAdjustment) {
      return aIsAdjustment ? 1 : -1;
    }

    return a.orderIndex - b.orderIndex;
  });
};
