import React, { useRef } from 'react';

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
          &ldquo;&rdquo;
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

export default MarkdownTextarea;
