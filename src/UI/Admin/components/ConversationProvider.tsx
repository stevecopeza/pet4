import React, { createContext, useState, useCallback, useEffect } from 'react';
import ConversationDrawer from './ConversationDrawer';

export interface ConversationOpenParams {
  contextType: string;
  contextId: string;
  contextVersion?: string;
  subject: string;
  subjectKey?: string;
  uuid?: string;
}

export interface ConversationContextValue {
  openConversation: (params: ConversationOpenParams) => void;
  closeConversation: () => void;
  isOpen: boolean;
}

export const ConversationContext = createContext<ConversationContextValue>({
  openConversation: () => {},
  closeConversation: () => {},
  isOpen: false,
});

const ConversationProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [params, setParams] = useState<ConversationOpenParams | null>(null);

  const openConversation = useCallback((p: ConversationOpenParams) => {
    setParams(p);
  }, []);

  const closeConversation = useCallback(() => {
    setParams(null);
  }, []);

  // Deep link: parse #discuss=type:id on mount
  useEffect(() => {
    try {
      const hash = window.location.hash || '';
      const m = hash.match(/discuss=([^:&]+):([^:&]+)/);
      if (m) {
        const contextType = decodeURIComponent(m[1]);
        const contextId = decodeURIComponent(m[2]);
        setParams({
          contextType,
          contextId,
          subject: `${contextType} #${contextId}`,
          subjectKey: `${contextType}:${contextId}`,
        });
        // Clean the hash fragment so it doesn't re-trigger on nav
        window.history.replaceState(null, '', window.location.pathname + window.location.search);
      }
    } catch {
      // ignore parse errors
    }
  }, []);

  const value: ConversationContextValue = {
    openConversation,
    closeConversation,
    isOpen: params !== null,
  };

  return (
    <ConversationContext.Provider value={value}>
      {children}
      <ConversationDrawer params={params} onClose={closeConversation} />
    </ConversationContext.Provider>
  );
};

export default ConversationProvider;
