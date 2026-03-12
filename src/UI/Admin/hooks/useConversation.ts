import { useContext } from 'react';
import { ConversationContext, ConversationContextValue } from '../components/ConversationProvider';

const useConversation = (): ConversationContextValue => {
  return useContext(ConversationContext);
};

export default useConversation;
