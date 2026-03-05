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
  const [tooltipPos, setTooltipPos] = useState<{ x: number; y: number }>({ x: 0, y: 0 });
  const clampedProgress = Math.min(Math.max(progress, 0), 100);

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
  const hoveredSeg = hoveredIndex !== null ? segments[hoveredIndex] : null;

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
                onClick={() => onSegmentClick?.(seg)}
              />
            );
          })}
        </div>
        {/* Remaining unfilled portion */}
        <div className="jb-unfilled" style={{ width: `${100 - clampedProgress}%` }} />
      </div>
      <span className="jb-progress-label">
        {clampedProgress}%
        {trajectoryLabel && <span className={`jb-traj ${trajectoryClass || ''}`} title={trajectoryTitle}>{trajectoryLabel}</span>}
      </span>

      {/* Portal tooltip — rendered at document.body to escape overflow:hidden */}
      {hoveredSeg && createPortal(
        <div className="jb-tooltip" style={{ left: tooltipPos.x, top: tooltipPos.y - 12 }}>
          <div className="jb-tooltip-state">
            <span className="jb-tooltip-dot" style={{ background: STATE_COLORS[hoveredSeg.state] }} />
            {STATE_LABELS[hoveredSeg.state] || hoveredSeg.state}
          </div>
          <div className="jb-tooltip-dates">
            {formatDate(hoveredSeg.start_at)} – {formatDate(hoveredSeg.end_at)}
          </div>
          <div className="jb-tooltip-duration">
            {formatDuration(hoveredSeg.duration_days)}
          </div>
          {hoveredSeg.reason && (
            <div className="jb-tooltip-reason">{hoveredSeg.reason}</div>
          )}
          {!hoveredSeg.reason && hoveredSeg.state === 'green' && segments.length === 1 && (
            <div className="jb-tooltip-reason">No health transitions recorded yet</div>
          )}
        </div>,
        document.body
      )}
    </div>
  );
};

export default JourneyBar;
