import React from 'react';

interface RoleBadgeProps {
  roleName: string | null | undefined;
}

const RoleBadge: React.FC<RoleBadgeProps> = ({ roleName }) => {
  const isUnset = !roleName;

  return (
    <span
      style={{
        display: 'inline-block',
        padding: '2px 8px',
        borderRadius: '10px',
        fontSize: '12px',
        lineHeight: '18px',
        whiteSpace: 'nowrap',
        backgroundColor: isUnset ? '#f0f0f0' : '#e8f0fe',
        color: isUnset ? '#999' : '#1a73e8',
        border: `1px solid ${isUnset ? '#ddd' : '#c6dafc'}`,
      }}
    >
      {isUnset ? 'No role' : roleName}
    </span>
  );
};

export default RoleBadge;
