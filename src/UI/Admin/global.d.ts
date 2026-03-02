export {};

declare global {
  interface Window {
    petSettings: {
      apiUrl: string;
      nonce: string;
      currentPage?: string;
      currentUserId?: number;
    };
  }
}
