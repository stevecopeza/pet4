import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import type { JourneySegment, JourneyTotals } from '../healthCompute';

interface JourneyBarProps {
  segments: JourneySegment[];
  progress: number; // 0–100 task completion %
  totals?: JourneyTotals;
  trajectoryLabel?: string;  // ▲ / ▼ / →
  trajectoryClass?: string;  // jb-traj-up / jb-traj-down / jb-traj-stable
  trajectoryTitle?: string;  // hover tooltip text
  onSegmentClick?: (segment: JourneySegment) => void;
}

const STATE_COLORS: Record<string, string> = {
  green: '#46b450',
  amber: '#ffb900',
  red: '#dc3232',
};

const STATE_LABELS: Record<string, string> = {
  green: 'Healthy',
  amber: 'At Risk',
  red: 'Critical',
};

function formatDate(iso: string | null): string {
  if (!iso) return 'now';
  const d = new Date(iso);
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatDuration(days: number): string {
  if (days < 1) return '< 1 day';
  if (days === 1) return '1 day';
  return `${Math.round(days)} days`;
}

const JourneyBar: React.FC<JourneyBarProps> = ({ segments, progress, trajectoryLabel, trajectoryClass, trajectoryTitle, onSegmentClick }) => {
  const [hoveredIndex, setHoveredIndex] = useState<number | null>(null);
  const [tappedIndex, setTappedIndex] = useState<number | null>(null);
  const [tooltipPos, setTooltipPos] = useState<{ x: number; y: number }>({ x: 0, y: 0 });
  const clampedProgress = Math.min(Math.max(progress, 0), 100);

  // Active tooltip: tapped (mobile) takes precedence over hovered (desktop)
  const activeIndex = tappedIndex !== null ? tappedIndex : hoveredIndex;

  // Empty/no-data state
  if (!segments || segments.length === 0) {
    return (
      <div className="jb-row">
        <div className="jb-bar">
          <div
            className="jb-segment"
            style={{ flex: 1, background: '#e9ecef' }}
            title="No timeline data available"
          />
        </div>
        <span className="jb-progress-label">—</span>
      </div>
    );
  }

  // Compute total duration for proportional widths
  const totalDuration = segments.reduce((sum, s) => sum + s.duration_days, 0);

  // Tooltip content — rendered via portal to escape overflow:hidden
  const activeSeg = activeIndex !== null ? segments[activeIndex] : null;

  return (
    <div className="jb-row">
      <div className="jb-bar">
        {/* Filled portion: journey segments scaled to completion % */}
        <div className="jb-filled" style={{ width: `${clampedProgress}%` }}>
          {segments.map((seg, i) => {
            const widthPct = totalDuration > 0
              ? Math.max((seg.duration_days / totalDuration) * 100, 1)
              : 100 / segments.length;

            return (
              <div
                key={i}
                className={`jb-segment ${onSegmentClick ? 'jb-clickable' : ''}`}
                style={{
                  flex: `${widthPct} 0 0%`,
                  background: STATE_COLORS[seg.state] || '#e9ecef',
                }}
                onMouseEnter={(e) => { setHoveredIndex(i); setTooltipPos({ x: e.clientX, y: e.clientY }); }}
                onMouseMove={(e) => setTooltipPos({ x: e.clientX, y: e.clientY })}
                onMouseLeave={() => setHoveredIndex(null)}
                onClick={(e) => {
                  // Mobile tap-to-toggle tooltip; desktop click-through to segment action
                  if ('ontouchstart' in window) {
                    e.preventDefault();
                    const rect = (e.target as HTMLElement).getBoundingClientRect();
                    setTooltipPos({ x: rect.left + rect.width / 2, y: rect.top });
                    setTappedIndex(prev => prev === i ? null : i);
                  } else {
                    onSegmentClick?.(seg);
                  }
                }}
              />
            );
          })}
        </div>
        {/* Remaining unfilled portion */}
        <div className="jb-unfilled" style={{ width: `${100 - clampedProgress}%` }} />
      </div>
      <span className="jb-progress-label" title={`Task completion: ${clampedProgress}%`}>
        {clampedProgress}%
        {trajectoryLabel && <span className={`jb-traj ${trajectoryClass || ''}`} title={trajectoryTitle}>{trajectoryLabel}</span>}
      </span>

      {/* Portal tooltip — rendered at document.body to escape overflow:hidden */}
      {activeSeg && createPortal(
        <div
          className="jb-tooltip"
          style={{ left: tooltipPos.x, top: tooltipPos.y - 12 }}
          onClick={() => setTappedIndex(null)} // dismiss on tap
        >
          <div className="jb-tooltip-header">Timeline</div>
          <div className="jb-tooltip-state">
            <span className="jb-tooltip-dot" style={{ background: STATE_COLORS[activeSeg.state] }} />
            {STATE_LABELS[activeSeg.state] || activeSeg.state}
          </div>
          <div className="jb-tooltip-dates">
            {formatDate(activeSeg.start_at)} – {formatDate(activeSeg.end_at)}
          </div>
          <div className="jb-tooltip-duration">
            {formatDuration(activeSeg.duration_days)}
          </div>
          {activeSeg.reason && (
            <div className="jb-tooltip-reason">{activeSeg.reason}</div>
          )}
          {!activeSeg.reason && activeSeg.state === 'green' && segments.length === 1 && (
            <div className="jb-tooltip-reason">No health transitions recorded yet</div>
          )}
          <div className="jb-tooltip-completion">Task completion: {clampedProgress}%</div>
        </div>,
        document.body
      )}
    </div>
  );
};

export default JourneyBar;
