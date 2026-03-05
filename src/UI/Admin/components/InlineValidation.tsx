import React from 'react';

export interface ValidationErrors {
  [fieldName: string]: string;
}

interface FieldValidationProps {
  error: string | undefined;
  children: React.ReactNode;
}

/** Wraps a form field with a red border and tooltip when an error is present. */
export const FieldValidation: React.FC<FieldValidationProps> = ({
  error,
  children,
}) => {
  return (
    <div style={{ position: 'relative' }}>
      <div
        style={{
          borderRadius: '3px',
          border: error ? '1px solid #d63638' : undefined,
        }}
      >
        {children}
      </div>
      {error && (
        <div
          style={{
            fontSize: '11px',
            color: '#d63638',
            marginTop: '2px',
            lineHeight: '14px',
          }}
        >
          {error}
        </div>
      )}
    </div>
  );
};

interface RowErrorSummaryProps {
  errors: ValidationErrors;
}

/** Shows a brief error count below an editing row when multiple fields are invalid. */
export const RowErrorSummary: React.FC<RowErrorSummaryProps> = ({ errors }) => {
  const errorCount = Object.keys(errors).length;
  if (errorCount === 0) return null;

  return (
    <div
      style={{
        padding: '4px 8px',
        fontSize: '12px',
        color: '#d63638',
        background: '#fcf0f0',
        borderTop: '1px solid #f0cccc',
      }}
    >
      Fix {errorCount} error{errorCount !== 1 ? 's' : ''} before saving.
    </div>
  );
};

interface ServerErrorBannerProps {
  message: string | null;
  onRetry?: () => void;
}

/** Red banner displayed below a row when a server-side save fails. */
export const ServerErrorBanner: React.FC<ServerErrorBannerProps> = ({
  message,
  onRetry,
}) => {
  if (!message) return null;

  return (
    <div
      style={{
        padding: '6px 10px',
        fontSize: '12px',
        color: '#fff',
        background: '#d63638',
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
      }}
    >
      <span>Save failed: {message}</span>
      {onRetry && (
        <button
          type="button"
          onClick={onRetry}
          style={{
            background: 'rgba(255,255,255,0.2)',
            border: '1px solid rgba(255,255,255,0.4)',
            color: '#fff',
            padding: '2px 8px',
            borderRadius: '3px',
            cursor: 'pointer',
            fontSize: '11px',
          }}
        >
          Try again
        </button>
      )}
    </div>
  );
};

/** Validate a block draft and return field-level errors. */
export const validateBlockDraft = (
  draft: Record<string, any>,
  blockType: string
): ValidationErrors => {
  const errors: ValidationErrors = {};

  if (blockType === 'TextBlock') {
    // Text blocks have minimal validation
    return errors;
  }

  if (blockType === 'PriceAdjustmentBlock') {
    if (!draft.description?.trim()) {
      errors.description = 'Description is required.';
    }
    return errors;
  }

  // Service / product / project blocks
  if (!draft.description?.trim()) {
    errors.description = 'Description is required.';
  }

  const qty = Number(draft.quantity);
  if (!Number.isFinite(qty) || qty < 1) {
    errors.quantity = 'Quantity must be at least 1.';
  }

  if (blockType !== 'OnceOffProjectBlock') {
    const price = Number(draft.sellValue ?? draft.unitPrice ?? 0);
    if (!Number.isFinite(price) || price < 0) {
      errors.price = 'Price cannot be negative.';
    }
  }

  return errors;
};
