'use client';

import { useState, useRef, useEffect } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { User, Bot, Copy, Check } from 'lucide-react';
import { refreshJwtToken, ensureValidToken } from '@/lib/auth';

type Message = {
  content: string;
  role: 'user' | 'assistant';
  timestamp: number;
  links?: Array<{ text: string; url: string }>;
  isCommand?: boolean;
  commandExecuted?: string;
  commandFailed?: boolean;
  needsConfirmation?: boolean;
  commandType?: string;
  commandPrompt?: string;
  needs_more_info?: boolean;
};

export default function ChatBot() {
  // Check for preview mode using the environment variable
  // This matches how preview mode is detected in the rest of the application
  const isPreviewMode = process.env.NEXT_PUBLIC_PREVIEW_MODE === 'true';

  // Don't render anything if not in preview mode
  if (!isPreviewMode) {
    return null;
  }

  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [copiedCode, setCopiedCode] = useState<string | null>(null);
  const [authError, setAuthError] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [loginUrl, setLoginUrl] = useState('');
  const [hasCheckedAuth, setHasCheckedAuth] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Function to test the connection to the API
  const testConnection = async () => {
    setIsLoading(true);
    try {
      const response = await fetch('/api/chat', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          messages: [
            {
              role: 'user',
              content: 'Test connection'
            }
          ]
        }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        setAuthError(true);
        if (errorData.wp_url) {
          setLoginUrl(`${errorData.wp_url}/wp-login.php`);
        }
        console.error('Connection test failed:', errorData.error);
      } else {
        // Connection successful, add a system message
        setAuthError(false); // Clear the auth error
        setMessages(prev => [
          ...prev,
          {
            content: "Connection restored! You can now continue chatting.",
            role: 'assistant',
            timestamp: Date.now()
          }
        ]);
      }
    } catch (error) {
      console.error('Connection test error:', error);
      setAuthError(true);
    } finally {
      setIsLoading(false);
    }
  };

  // Add welcome message when chat is opened
  useEffect(() => {
    if (isOpen && messages.length === 0) {
      setMessages([
        {
          content: "👋 Hi! I'm the PressX Assistant. I'm here to help you create a landing page for your PressX website. Just tell me what your page is about, and I'll build it for you.",
          role: 'assistant',
          timestamp: Date.now()
        }
      ]);
    }
  }, [isOpen, messages.length]);

  // Check for JWT token when component mounts
  useEffect(() => {
    // Only run this once
    if (!hasCheckedAuth && typeof window !== 'undefined') {
      // Check if we have a from_login parameter in the URL
      const urlParams = new URLSearchParams(window.location.search);
      const fromLogin = urlParams.get('from_login');

      if (fromLogin === 'true') {
        // Remove the query parameter
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);

        // Test the connection
        testConnection();
      } else {
        // Automatically try to refresh the token on component mount
        // This helps with expired tokens without requiring user interaction
        ensureValidToken().then(success => {
          if (success) {
            console.log('Token automatically refreshed and validated on component mount');
          } else {
            console.log('Automatic token refresh failed, will prompt user if needed');
          }
        });
      }

      setHasCheckedAuth(true);
    }
  }, [hasCheckedAuth]);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  // Add effect to focus input when messages change
  useEffect(() => {
    if (!isLoading && inputRef.current && !authError) {
      inputRef.current.focus();
    }
  }, [isLoading, authError]);

  // Reset copied state after 2 seconds
  useEffect(() => {
    if (copiedCode) {
      const timeout = setTimeout(() => {
        setCopiedCode(null);
      }, 2000);
      return () => clearTimeout(timeout);
    }
  }, [copiedCode]);

  // Function to directly refresh the JWT token
  const refreshToken = async () => {
    setIsRefreshing(true);
    try {
      console.log('Attempting to refresh JWT token...');
      const success = await refreshJwtToken();

      if (success) {
        // Token refreshed successfully
        console.log('Token refresh successful');
        setAuthError(false);
        setMessages(prev => [
          ...prev,
          {
            content: "Authentication token refreshed successfully. You can continue chatting now.",
            role: 'assistant',
            timestamp: Date.now(),
          }
        ]);
        return true;
      } else {
        // Token refresh failed, fallback to manual login
        console.error('Token refresh failed');

        // Add more detailed error message
        setMessages(prev => [
          ...prev,
          {
            content: "Failed to refresh authentication token. Please try logging in again.",
            role: 'assistant',
            timestamp: Date.now(),
          }
        ]);
        return false;
      }
    } catch (error) {
      console.error('Failed to refresh token:', error);

      // Add error message for the user
      setMessages(prev => [
        ...prev,
        {
          content: `Error refreshing token: ${error instanceof Error ? error.message : 'Unknown error'}. Please try logging in again.`,
          role: 'assistant',
          timestamp: Date.now(),
        }
      ]);
      return false;
    } finally {
      setIsRefreshing(false);
    }
  };

  // Function to refresh the session
  const refreshSession = async () => {
    setIsRefreshing(true);
    try {
      // First try to refresh the token directly
      const tokenRefreshed = await refreshToken();

      if (tokenRefreshed) {
        return; // Token refreshed successfully, no need to open login page
      }

      // If token refresh failed, open WordPress login in a new tab
      if (loginUrl) {
        window.open(loginUrl, '_blank');
      } else {
        // If we don't have a login URL, use the wp_url from the API response
        const response = await fetch('/api/chat', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            messages: [{ role: 'user', content: 'test' }]
          }),
        });

        if (response.status === 401) {
          const data = await response.json();
          if (data.wp_url) {
            window.open(`${data.wp_url}/wp-login.php`, '_blank');
          }
        }
      }

      // Show instructions to the user
      setMessages(prev => [
        ...prev,
        {
          content: "I've opened WordPress login in a new tab. After logging in, please come back to this page and click the 'Refresh Connection' button below.\n\n**Important:** After logging in to WordPress, you need to refresh this page to get a new authentication token.",
          role: 'assistant',
          timestamp: Date.now()
        }
      ]);

    } catch (error) {
      console.error('Failed to refresh session:', error);
    } finally {
      setIsRefreshing(false);
    }
  };

  // Function to manually check connection after login
  const checkConnection = async () => {
    setIsLoading(true);
    try {
      // First try to refresh the token directly
      const tokenRefreshed = await refreshToken();

      if (tokenRefreshed) {
        setIsLoading(false);
        return; // Token refreshed successfully
      }

      // If token refresh failed, reload the page
      window.location.reload();
    } catch (error) {
      console.error('Connection refresh error:', error);
      setIsLoading(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!input.trim() || isLoading) return;

    const userMessage: Message = {
      content: input.trim(),
      role: 'user',
      timestamp: Date.now(),
    };

    setMessages((prev) => [...prev, userMessage]);
    setInput('');
    setIsLoading(true);
    setAuthError(false);

    // Focus the input field after submission - using a longer timeout to ensure DOM updates complete
    setTimeout(() => {
      if (inputRef.current) {
        inputRef.current.focus();
        console.log("Focus set on input field");
      }
    }, 100);

    try {
      // Check if this is a confirmation response (yes/no) to a command
      const lastMessage = messages[messages.length - 1];
      const isConfirmation = lastMessage?.needsConfirmation &&
        (input.toLowerCase() === 'yes' || input.toLowerCase() === 'no');

      // Check if this is a response to a request for more information
      const isMoreInfoResponse = lastMessage?.isCommand && lastMessage?.needs_more_info === true;

      interface ChatRequestBody {
        messages: { role: string; content: string }[];
        confirmed?: string;
        command_type?: string;
        command_prompt?: string;
        needs_more_info?: boolean;
      }

      let requestBody: ChatRequestBody = {
        messages: [
          {
            role: 'user',
            content: userMessage.content
          }
        ]
      };

      // If this is a confirmation response, add the necessary parameters
      if (isConfirmation) {
        requestBody.confirmed = input.toLowerCase();
        requestBody.command_type = lastMessage.commandType;
        requestBody.command_prompt = lastMessage.commandPrompt;
      }

      // If this is a response to a request for more information
      if (isMoreInfoResponse) {
        requestBody.command_type = lastMessage.commandType || 'landing_page';
        requestBody.command_prompt = userMessage.content; // Use the user's response as the prompt
        requestBody.needs_more_info = true;
      }

      const response = await fetch('/api/chat', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        cache: 'no-store',  // Prevent caching
        body: JSON.stringify(requestBody),
      });

      if (!response.ok) {
        const errorData = await response.json();
        console.error('API Error:', errorData);

        // Handle authentication errors
        if (response.status === 401) {
          setAuthError(true);
          // Store the WordPress URL if provided
          if (errorData.wp_url) {
            setLoginUrl(`${errorData.wp_url}/wp-login.php`);
          }

          // Check if this is a token expiration issue
          const isExpiredToken =
            errorData.raw_error?.message?.includes('Expired token') ||
            errorData.message?.includes('Expired token') ||
            errorData.expired_token === true;

          if (isExpiredToken) {
            console.log('Detected expired token, attempting automatic refresh');
            // Try to refresh the token automatically
            const tokenRefreshed = await refreshToken();
            if (tokenRefreshed) {
              // If token refreshed successfully, retry the request
              setIsLoading(false);
              return handleSubmit(e);
            }
          } else {
            // Try to refresh the token automatically for other auth errors
            const tokenRefreshed = await refreshToken();
            if (tokenRefreshed) {
              // If token refreshed successfully, retry the request
              setIsLoading(false);
              return handleSubmit(e);
            }
          }

          throw new Error(errorData.message || errorData.error || 'Authentication required. Please refresh your token.');
        } else if (response.status === 403) {
          throw new Error('Chat is only available in preview mode.');
        } else {
          throw new Error(errorData.message || errorData.error || 'Failed to get response');
        }
      }

      const data = await response.json();
      console.log('API Response:', data);

      // Check if this was a command response
      const isCommand = data.command_detected || data.command_executed;
      const needsConfirmation = data.needs_confirmation === true;

      const assistantMessage: Message = {
        content: data.content || data.response || 'Sorry, I could not generate a response.',
        role: 'assistant',
        timestamp: Date.now(),
        links: data.links,
        isCommand: !!isCommand,
        commandExecuted: data.command_executed,
        commandFailed: data.command_failed,
        needsConfirmation: needsConfirmation,
        commandType: data.command_type,
        commandPrompt: data.command_prompt,
        needs_more_info: data.needs_more_info,
      };

      setMessages((prev) => [...prev, assistantMessage]);
    } catch (error) {
      console.error('Chat error:', error);

      // Check for specific error messages
      let errorMessage: Message;
      const errorString = error instanceof Error ? error.message : String(error);

      if (errorString.includes('WordPress URL is not configured')) {
        errorMessage = {
          content: 'The chat feature is not properly configured. The WordPress URL is missing. Please contact the site administrator.',
          role: 'assistant',
          timestamp: Date.now(),
        };
      } else if (errorString.includes('Expired token')) {
        // Set auth error to true to display the refresh token button
        setAuthError(true);
        errorMessage = {
          content: 'Your authentication token has expired. Please look for the "Refresh Token" button in the chat window and click it to get a new token.',
          role: 'assistant',
          timestamp: Date.now(),
        };
      } else {
        errorMessage = {
          content: error instanceof Error ? error.message : 'Sorry, I encountered an error. Please try again.',
          role: 'assistant',
          timestamp: Date.now(),
        };
      }

      setMessages((prev) => [...prev, errorMessage]);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="fixed bottom-5 right-5 z-50">
      {!isOpen ? (
        <button
          onClick={() => setIsOpen(true)}
          className="bg-primary hover:bg-primary/90 text-white px-6 py-3 rounded-full font-medium shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 flex items-center gap-2"
          aria-label="Open chat"
        >
          <Bot className="w-5 h-5" />
          <span>AI Page Creator</span>
        </button>
      ) : (
        <div className="w-[450px] bg-white rounded-xl shadow-2xl overflow-hidden flex flex-col min-h-[400px] max-h-[600px] border border-gray-200">
          <div className="bg-gray-100 p-4 flex justify-between items-center border-b border-gray-200">
            <div className="flex items-center gap-2">
              <Bot className="w-5 h-5 text-primary" />
              <h3 className="text-lg font-semibold text-gray-800">AI Page Creator</h3>
            </div>
            <button
              onClick={() => setIsOpen(false)}
              className="text-gray-500 hover:text-gray-700 p-1 hover:bg-gray-200 rounded transition-colors duration-200"
              aria-label="Close chat"
            >
              <span className="text-xl">✕</span>
            </button>
          </div>
          <div className="flex-1 overflow-y-auto p-4 space-y-4 min-h-0 bg-white">
            {messages.map((message) => (
              <div
                key={message.timestamp}
                className={`flex items-start gap-2 ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
              >
                {message.role === 'assistant' && (
                  <Bot className="w-6 h-6 text-primary mt-1" />
                )}
                <div
                  className={`max-w-[85%] rounded-lg p-3 ${message.role === 'user'
                    ? 'bg-primary text-white'
                    : message.isCommand && !message.commandFailed
                      ? 'bg-green-50 text-gray-800 border border-green-200'
                      : message.isCommand && message.commandFailed
                        ? 'bg-amber-50 text-gray-800 border border-amber-200'
                        : 'bg-gray-100 text-gray-800'
                    }`}
                >
                  {message.role === 'user' ? (
                    message.content
                  ) : (
                    <div>
                      <div className="prose max-w-none [&_p]:mt-0 [&_p]:mb-2 last:[&_p]:mb-0 prose-a:text-primary prose-code:text-gray-800 prose-pre:bg-gray-100 prose-pre:text-gray-800 prose-li:text-gray-800 prose-p:text-gray-800 [&_ul]:text-gray-800 [&_li]:marker:text-gray-800 [&_ol]:text-gray-800 prose-strong:text-gray-900 [&_strong]:font-bold">
                        <ReactMarkdown
                          remarkPlugins={[remarkGfm]}
                          components={{
                            a: ({ children, ...props }: any) => (
                              <a {...props} target="_blank" rel="noopener noreferrer" className="text-primary hover:text-primary/80 bg-primary/10 hover:bg-primary/20 px-1 rounded transition-colors">
                                {children}
                              </a>
                            ),
                            code: ({ inline, className, children, ...props }: any) => {
                              const code = String(children).replace(/\n$/, '');

                              if (inline) {
                                return (
                                  <code className="bg-gray-100 text-gray-800 px-1.5 py-0.5 rounded font-mono text-sm" {...props}>
                                    {children}
                                  </code>
                                );
                              }

                              const isCopied = copiedCode === code;

                              return (
                                <div className="relative group">
                                  <button
                                    onClick={() => {
                                      navigator.clipboard.writeText(code);
                                      setCopiedCode(code);
                                    }}
                                    className="absolute right-2 top-2 p-2 rounded-lg bg-gray-200 text-gray-600 hover:text-gray-800 opacity-0 group-hover:opacity-100 transition-opacity"
                                    aria-label="Copy code"
                                  >
                                    {isCopied ? (
                                      <Check className="w-4 h-4 text-green-600" />
                                    ) : (
                                      <Copy className="w-4 h-4" />
                                    )}
                                  </button>
                                  <pre className="bg-gray-50 ring-1 ring-gray-200 text-gray-800 p-3 rounded-lg font-mono text-sm overflow-x-auto my-2">
                                    <code className={className} {...props}>
                                      {children}
                                    </code>
                                  </pre>
                                </div>
                              );
                            }
                          }}
                        >
                          {message.content}
                        </ReactMarkdown>
                      </div>
                      {message.needsConfirmation && (
                        <div className="mt-2 pt-2 border-t border-gray-200">
                          <div className="text-xs font-medium text-gray-500 mb-1.5">Please confirm:</div>
                          <div className="flex flex-wrap gap-2">
                            <button
                              onClick={() => {
                                const userMessage: Message = {
                                  content: 'yes',
                                  role: 'user',
                                  timestamp: Date.now(),
                                };
                                setMessages((prev) => [...prev, userMessage]);
                                setIsLoading(true);
                                setAuthError(false);

                                // Prepare the request body with confirmation parameters
                                const requestBody = {
                                  messages: [{ role: 'user', content: 'yes' }],
                                  confirmed: 'yes',
                                  command_type: message.commandType,
                                  command_prompt: message.commandPrompt,
                                };

                                // Send the request directly
                                fetch('/api/chat', {
                                  method: 'POST',
                                  headers: {
                                    'Content-Type': 'application/json',
                                  },
                                  cache: 'no-store',
                                  body: JSON.stringify(requestBody),
                                })
                                  .then(async (response) => {
                                    if (!response.ok) {
                                      const errorData = await response.json();
                                      console.error('API Error:', errorData);

                                      // Handle authentication errors
                                      if (response.status === 401) {
                                        setAuthError(true);
                                        if (errorData.wp_url) {
                                          setLoginUrl(`${errorData.wp_url}/wp-login.php`);
                                        }

                                        // Try to refresh token if expired
                                        const isExpiredToken =
                                          errorData.raw_error?.message?.includes('Expired token') ||
                                          errorData.message?.includes('Expired token') ||
                                          errorData.expired_token === true;

                                        if (isExpiredToken) {
                                          const tokenRefreshed = await refreshToken();
                                          if (tokenRefreshed) {
                                            // Retry the request if token refreshed
                                            return fetch('/api/chat', {
                                              method: 'POST',
                                              headers: {
                                                'Content-Type': 'application/json',
                                              },
                                              cache: 'no-store',
                                              body: JSON.stringify(requestBody),
                                            });
                                          }
                                        } else {
                                          // Try to refresh token for other auth errors
                                          const tokenRefreshed = await refreshToken();
                                          if (tokenRefreshed) {
                                            // Retry the request if token refreshed
                                            return fetch('/api/chat', {
                                              method: 'POST',
                                              headers: {
                                                'Content-Type': 'application/json',
                                              },
                                              cache: 'no-store',
                                              body: JSON.stringify(requestBody),
                                            });
                                          }
                                        }

                                        throw new Error(errorData.message || errorData.error || 'Authentication required. Please refresh your token.');
                                      } else if (response.status === 403) {
                                        throw new Error('Chat is only available in preview mode.');
                                      } else {
                                        throw new Error(errorData.message || errorData.error || 'Failed to get response');
                                      }
                                    }
                                    return response.json();
                                  })
                                  .then((data) => {
                                    console.log('API Response:', data);

                                    // Check if this was a command response
                                    const isCommand = data.command_detected || data.command_executed;
                                    const needsConfirmation = data.needs_confirmation === true;

                                    const assistantMessage: Message = {
                                      content: data.content || data.response || 'Sorry, I could not generate a response.',
                                      role: 'assistant',
                                      timestamp: Date.now(),
                                      links: data.links,
                                      isCommand: !!isCommand,
                                      commandExecuted: data.command_executed,
                                      commandFailed: data.command_failed,
                                      needsConfirmation: needsConfirmation,
                                      commandType: data.command_type,
                                      commandPrompt: data.command_prompt,
                                      needs_more_info: data.needs_more_info,
                                    };

                                    setMessages((prev) => [...prev, assistantMessage]);
                                  })
                                  .catch((error) => {
                                    console.error('Chat error:', error);

                                    // Check for specific error messages
                                    let errorMessage: Message;
                                    const errorString = error instanceof Error ? error.message : String(error);

                                    if (errorString.includes('WordPress URL is not configured')) {
                                      errorMessage = {
                                        content: 'The chat feature is not properly configured. The WordPress URL is missing. Please contact the site administrator.',
                                        role: 'assistant',
                                        timestamp: Date.now(),
                                      };
                                    } else if (errorString.includes('Expired token')) {
                                      // Set auth error to true to display the refresh token button
                                      setAuthError(true);
                                      errorMessage = {
                                        content: 'Your authentication token has expired. Please look for the "Refresh Token" button in the chat window and click it to get a new token.',
                                        role: 'assistant',
                                        timestamp: Date.now(),
                                      };
                                    } else {
                                      errorMessage = {
                                        content: error instanceof Error ? error.message : 'Sorry, I encountered an error. Please try again.',
                                        role: 'assistant',
                                        timestamp: Date.now(),
                                      };
                                    }

                                    setMessages((prev) => [...prev, errorMessage]);
                                  })
                                  .finally(() => {
                                    setIsLoading(false);
                                  });
                              }}
                              className="text-sm font-semibold flex items-center gap-1.5 px-3 py-1.5 rounded-md transition-colors text-green-700 hover:text-green-800 bg-green-100 hover:bg-green-200"
                            >
                              Yes
                            </button>
                            <button
                              onClick={() => {
                                const userMessage: Message = {
                                  content: 'no',
                                  role: 'user',
                                  timestamp: Date.now(),
                                };
                                setMessages((prev) => [...prev, userMessage]);
                                setIsLoading(true);
                                setAuthError(false);

                                // Prepare the request body with confirmation parameters
                                const requestBody = {
                                  messages: [{ role: 'user', content: 'no' }],
                                  confirmed: 'no',
                                  command_type: message.commandType,
                                  command_prompt: message.commandPrompt,
                                };

                                // Send the request directly
                                fetch('/api/chat', {
                                  method: 'POST',
                                  headers: {
                                    'Content-Type': 'application/json',
                                  },
                                  cache: 'no-store',
                                  body: JSON.stringify(requestBody),
                                })
                                  .then(async (response) => {
                                    if (!response.ok) {
                                      const errorData = await response.json();
                                      console.error('API Error:', errorData);

                                      // Handle authentication errors
                                      if (response.status === 401) {
                                        setAuthError(true);
                                        if (errorData.wp_url) {
                                          setLoginUrl(`${errorData.wp_url}/wp-login.php`);
                                        }

                                        // Try to refresh token if expired
                                        const isExpiredToken =
                                          errorData.raw_error?.message?.includes('Expired token') ||
                                          errorData.message?.includes('Expired token') ||
                                          errorData.expired_token === true;

                                        if (isExpiredToken) {
                                          const tokenRefreshed = await refreshToken();
                                          if (tokenRefreshed) {
                                            // Retry the request if token refreshed
                                            return fetch('/api/chat', {
                                              method: 'POST',
                                              headers: {
                                                'Content-Type': 'application/json',
                                              },
                                              cache: 'no-store',
                                              body: JSON.stringify(requestBody),
                                            });
                                          }
                                        } else {
                                          // Try to refresh token for other auth errors
                                          const tokenRefreshed = await refreshToken();
                                          if (tokenRefreshed) {
                                            // Retry the request if token refreshed
                                            return fetch('/api/chat', {
                                              method: 'POST',
                                              headers: {
                                                'Content-Type': 'application/json',
                                              },
                                              cache: 'no-store',
                                              body: JSON.stringify(requestBody),
                                            });
                                          }
                                        }

                                        throw new Error(errorData.message || errorData.error || 'Authentication required. Please refresh your token.');
                                      } else if (response.status === 403) {
                                        throw new Error('Chat is only available in preview mode.');
                                      } else {
                                        throw new Error(errorData.message || errorData.error || 'Failed to get response');
                                      }
                                    }
                                    return response.json();
                                  })
                                  .then((data) => {
                                    console.log('API Response:', data);

                                    // Check if this was a command response
                                    const isCommand = data.command_detected || data.command_executed;
                                    const needsConfirmation = data.needs_confirmation === true;

                                    const assistantMessage: Message = {
                                      content: data.content || data.response || 'Sorry, I could not generate a response.',
                                      role: 'assistant',
                                      timestamp: Date.now(),
                                      links: data.links,
                                      isCommand: !!isCommand,
                                      commandExecuted: data.command_executed,
                                      commandFailed: data.command_failed,
                                      needsConfirmation: needsConfirmation,
                                      commandType: data.command_type,
                                      commandPrompt: data.command_prompt,
                                      needs_more_info: data.needs_more_info,
                                    };

                                    setMessages((prev) => [...prev, assistantMessage]);
                                  })
                                  .catch((error) => {
                                    console.error('Chat error:', error);

                                    // Check for specific error messages
                                    let errorMessage: Message;
                                    const errorString = error instanceof Error ? error.message : String(error);

                                    if (errorString.includes('WordPress URL is not configured')) {
                                      errorMessage = {
                                        content: 'The chat feature is not properly configured. The WordPress URL is missing. Please contact the site administrator.',
                                        role: 'assistant',
                                        timestamp: Date.now(),
                                      };
                                    } else if (errorString.includes('Expired token')) {
                                      // Set auth error to true to display the refresh token button
                                      setAuthError(true);
                                      errorMessage = {
                                        content: 'Your authentication token has expired. Please look for the "Refresh Token" button in the chat window and click it to get a new token.',
                                        role: 'assistant',
                                        timestamp: Date.now(),
                                      };
                                    } else {
                                      errorMessage = {
                                        content: error instanceof Error ? error.message : 'Sorry, I encountered an error. Please try again.',
                                        role: 'assistant',
                                        timestamp: Date.now(),
                                      };
                                    }

                                    setMessages((prev) => [...prev, errorMessage]);
                                  })
                                  .finally(() => {
                                    setIsLoading(false);
                                  });
                              }}
                              className="text-sm font-semibold flex items-center gap-1.5 px-3 py-1.5 rounded-md transition-colors text-red-700 hover:text-red-800 bg-red-100 hover:bg-red-200"
                            >
                              No
                            </button>
                          </div>
                        </div>
                      )}
                      {message.links && message.links.length > 0 && (
                        <div className="mt-2 pt-2 border-t border-gray-200">
                          <div className="text-xs font-medium text-gray-500 mb-1.5">Related Links:</div>
                          <div className="flex flex-wrap gap-2">
                            {message.links.map((link, index) => (
                              <a
                                key={index}
                                href={link.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={`text-sm font-semibold flex items-center gap-1.5 px-3 py-1.5 rounded-md transition-colors ${message.commandExecuted === 'create_ai_landing'
                                  ? 'text-green-700 hover:text-green-800 bg-green-100 hover:bg-green-200'
                                  : 'text-primary hover:text-primary/80 bg-primary/10 hover:bg-primary/20'
                                  }`}
                              >
                                {link.text}
                              </a>
                            ))}
                          </div>
                        </div>
                      )}
                      {message.commandExecuted && (
                        <div className="mt-2 text-xs text-gray-500">
                          {message.commandFailed
                            ? '❌ Command failed'
                            : '✅ Command executed successfully'}
                        </div>
                      )}
                    </div>
                  )}
                </div>
                {message.role === 'user' && (
                  <User className="w-6 h-6 text-white bg-primary rounded-full p-1 mt-1" />
                )}
              </div>
            ))}
            {isLoading && (
              <div className="flex justify-start items-center gap-2">
                <Bot className="w-6 h-6 text-primary mt-1" />
                <div className="bg-gray-100 text-gray-800 rounded-lg p-3 animate-pulse">
                  Thinking...
                </div>
              </div>
            )}
            {authError && (
              <div className="flex justify-center my-4">
                <div className="bg-amber-50 text-amber-800 border border-amber-200 rounded-lg p-3 text-sm w-full">
                  <p className="mb-2">Your session has expired. Please refresh your authentication token to continue.</p>
                  <div className="flex flex-col space-y-2">
                    <button
                      onClick={refreshToken}
                      disabled={isRefreshing}
                      className="w-full bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium"
                    >
                      {isRefreshing ? 'Refreshing token...' : 'Refresh Token'}
                    </button>
                  </div>
                </div>
              </div>
            )}
            <div ref={messagesEndRef} />
          </div>
          <form onSubmit={handleSubmit} className="p-4 border-t border-gray-200 bg-white">
            <div className="flex space-x-2">
              <input
                type="text"
                value={input}
                onChange={(e) => setInput(e.target.value)}
                placeholder="What your landing page is about?"
                className="flex-1 p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-gray-800 bg-white placeholder-gray-400"
                disabled={isLoading || authError}
                ref={inputRef}
              />
              <button
                type="submit"
                disabled={isLoading || !input.trim() || authError}
                className="bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Send
              </button>
            </div>
            {authError && (
              <div className="mt-2 text-xs text-amber-600 text-center">
                Click the "Refresh Token" button above to automatically reconnect.
              </div>
            )}
          </form>
        </div>
      )}
    </div>
  );
}
