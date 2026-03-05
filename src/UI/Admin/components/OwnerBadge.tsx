import React from 'react';

interface OwnerBadgeProps {
  ownerName: string | null | undefined;
  ownerType: 'employee' | 'team' | '' | null | undefined;
}

const OwnerBadge: React.FC<OwnerBadgeProps> = ({ ownerName, ownerType }) => {
  const isUnset = !ownerName;
  const isTeam = ownerType === 'team';

  return (
    <span
      style={{
        display: 'inline-block',
        padding: '2px 8px',
        borderRadius: '10px',
        fontSize: '12px',
        lineHeight: '18px',
        whiteSpace: 'nowrap',
        backgroundColor: isUnset ? '#f0f0f0' : isTeam ? '#fef7e0' : '#e8f5e9',
        color: isUnset ? '#999' : isTeam ? '#b06d0f' : '#2e7d32',
        border: `1px solid ${isUnset ? '#ddd' : isTeam ? '#f5deb3' : '#a5d6a7'}`,
      }}
    >
      {isUnset ? 'Unassigned' : ownerName}
    </span>
  );
};

export default OwnerBadge;
