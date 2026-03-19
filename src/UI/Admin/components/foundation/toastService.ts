type ToastApi = {
  success: (message: string) => void;
  error: (message: string) => void;
  info: (message: string) => void;
};

let api: ToastApi | null = null;

export const registerToastApi = (nextApi: ToastApi | null) => {
  api = nextApi;
};

const fallbackLog = (message: string) => {
  console.warn(message);
};

export const toast = {
  success: (message: string) => {
    if (api) {
      api.success(message);
      return;
    }
    fallbackLog(message);
  },
  error: (message: string) => {
    if (api) {
      api.error(message);
      return;
    }
    fallbackLog(message);
  },
  info: (message: string) => {
    if (api) {
      api.info(message);
      return;
    }
    fallbackLog(message);
  },
};

export default toast;
