'use client';

import { useState, useRef, useEffect } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { User } from 'lucide-react';
import { Bot } from 'lucide-react';
import { Copy, Check } from 'lucide-react';
import { refreshJwtToken, ensureValidToken } from '@/lib/auth';
import { useRouter } from 'next/navigation';

type Message = {
  content: string;
  role: 'user' | 'assistant';
  timestamp: number;
  id?: string;
  links?: Array<{ text: string; url: string; command?: string }>;
  isCommand?: boolean;
  commandExecuted?: string;
  commandFailed?: boolean;
  needsConfirmation?: boolean;
  commandType?: string;
  commandPrompt?: string;
  needs_more_info?: boolean;
  section_type?: string;
  page_id?: number;
};

// Define the interface at the component level
interface ConfirmationRequestBody {
  messages: { role: string; content: string }[];
  confirmed: string;
  command_type?: string;
  command_prompt?: string;
  section_type?: string;
  page_id?: number;
  message: string;
  needs_more_info?: boolean;
}

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
  const router = useRouter();

  // Function to generate a unique ID
  const generateUniqueId = () => {
    return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
  };

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
            timestamp: Date.now(),
            id: generateUniqueId(),
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
      const welcomeMessage: Message = {
        content: "👋 Hi there! I'm the PressX ChatBot. I can help you create landing pages or add sections to existing ones.\n\nCommands:",
        role: 'assistant',
        timestamp: Date.now(),
        id: generateUniqueId(),
        links: [
          {
            text: 'landing page',
            url: '#add-landing',
            command: 'add landing',
          },
          {
            text: 'hero',
            url: '#add-hero',
            command: 'add hero section',
          },
          {
            text: 'text',
            url: '#add-text',
            command: 'add text section',
          },
          {
            text: 'quote',
            url: '#add-quote',
            command: 'add quote section',
          },
          {
            text: 'side by side',
            url: '#add-side-by-side',
            command: 'add side_by_side section',
          },
          {
            text: 'card group',
            url: '#add-card-group',
            command: 'add card_group section',
          },
          {
            text: 'gallery',
            url: '#add-gallery',
            command: 'add gallery section',
          },
          {
            text: 'accordion',
            url: '#add-accordion',
            command: 'add accordion section',
          },
          {
            text: 'newsletter',
            url: '#add-newsletter',
            command: 'add newsletter section',
          },
        ],
      };

      setMessages([welcomeMessage]);
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
            id: generateUniqueId(),
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
            id: generateUniqueId(),
          }
        ]);
        return false;
      }
    } catch (error) {
      console.error('Failed to refresh token:', error);

      // Handle authentication errors
      setMessages(prev => [
        ...prev,
        {
          content: `Error refreshing token: ${error instanceof Error ? error.message : 'Unknown error'}. Please try logging in again.`,
          role: 'assistant',
          timestamp: Date.now(),
          id: generateUniqueId(),
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
          timestamp: Date.now(),
          id: generateUniqueId(),
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

  // Function to handle section addition
  const handleSectionAdded = (data: any) => {
    if (data.section_added && data.section_data) {
      console.log('Section added successfully:', data.section_data.type);

      try {
        // Get section data
        const section = data.section_data;
        const sectionType = section.type || 'generic';
        const pageUrl = section.page_url || '';

        // Create a message with refresh button
        const refreshMessage: Message = {
          content: `✅ New ${sectionType} section added successfully! Click the button below to refresh and see your changes.`,
          role: 'assistant',
          timestamp: Date.now(),
          id: generateUniqueId(),
          links: [
            {
              text: '🔄 Refresh Page',
              url: pageUrl || window.location.href
            }
          ],
          isCommand: true,
          commandExecuted: 'add_section'
        };

        // Replace the last message with our refresh message
        setMessages(prev => {
          // Remove the last message (which is the regular response)
          const newMessages = [...prev];
          newMessages.pop();

          // Add our refresh message
          return [...newMessages, refreshMessage];
        });

        // Note: We no longer need to scroll the page here
        // The 't' parameter in the URL will trigger scrolling after refresh
      } catch (error) {
        console.error('Error handling section addition:', error);
      }
    }
  };

  // Add effect to check for 't' parameter in URL and scroll to bottom if present
  useEffect(() => {
    // Check if we have the 't' parameter in the URL (timestamp for cache busting)
    const urlParams = new URLSearchParams(window.location.search);
    const hasTimestampParam = urlParams.has('t');

    if (hasTimestampParam) {
      console.log('Detected timestamp parameter, scrolling to bottom of page');

      window.scrollTo({
        top: document.documentElement.scrollHeight,
        behavior: 'smooth'
      });
    }
  }, []);  // Empty dependency array means this runs once on component mount

  // Handle submit
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    // Get the last message to check if we need to handle confirmation
    const lastMessage = messages[messages.length - 1];
    const isConfirmation = lastMessage && lastMessage.needsConfirmation;

    if (!input.trim() && !isConfirmation) {
      return;
    }

    // Don't allow submission while loading
    if (isLoading) {
      return;
    }

    setIsLoading(true);

    try {
      // Create a new user message
      const userMessage: Message = {
        content: input,
        role: 'user',
        timestamp: Date.now(),
        id: generateUniqueId(),
      };

      // Add the user message to the chat
      setMessages((prev) => [...prev, userMessage]);

      // Clear the input
      setInput('');

      // Check if this is a response to a request for more information
      const isMoreInfoResponse = lastMessage && lastMessage.needs_more_info;

      // Prepare the request body
      interface ChatRequestBody {
        messages: { role: string; content: string }[];
        confirmed?: string;
        command_type?: string;
        command_prompt?: string;
        section_type?: string;
        page_id?: number;
        needs_more_info?: boolean;
      }

      const requestBody: ChatRequestBody = {
        messages: messages
          .concat(userMessage)
          .map((msg) => ({ role: msg.role, content: msg.content })),
      };

      // If this is a confirmation response, add the necessary parameters
      if (isConfirmation) {
        requestBody.confirmed = input.toLowerCase();
        requestBody.command_type = lastMessage.commandType;
        requestBody.command_prompt = lastMessage.commandPrompt;

        // If this is an add_section command, include the section_type and page_id
        if (lastMessage.commandType === 'add_section') {
          // Ensure section_type is in the correct format
          let apiSectionType = lastMessage.section_type;

          // Handle special cases
          if (lastMessage.section_type === 'side-by-side') {
            apiSectionType = 'side_by_side';
          } else if (lastMessage.section_type === 'card-group') {
            apiSectionType = 'card_group';
          }

          requestBody.section_type = apiSectionType;

          // Include the page_id from the message if it exists
          if (lastMessage.page_id) {
            requestBody.page_id = lastMessage.page_id;
            console.log('Debug - Including page ID in confirmation:', lastMessage.page_id);
          } else {
            // Fallback: try to get the current page ID if we're on a landing page
            const pageElement = document.querySelector('[data-post-type="landing"]');
            if (pageElement) {
              const pageId = pageElement.getAttribute('data-post-id');
              if (pageId) {
                requestBody.page_id = parseInt(pageId, 10);
                console.log('Debug - Found page ID for confirmation:', requestBody.page_id);
              }
            }
          }
        }
      }

      // If this is a response to a request for more information
      if (isMoreInfoResponse) {
        requestBody.command_type = lastMessage.commandType || 'landing_page';
        requestBody.command_prompt = userMessage.content; // Use the user's response as the prompt
        requestBody.needs_more_info = true;

        // If this is an add_section command, include the section_type and page_id
        if (lastMessage.commandType === 'add_section') {
          requestBody.section_type = lastMessage.section_type;

          // Get the current page ID if we're on a landing page
          const pageElement = document.querySelector('[data-post-type="landing"]');
          if (pageElement) {
            const pageId = pageElement.getAttribute('data-post-id');
            if (pageId) {
              requestBody.page_id = parseInt(pageId, 10);
            }
          }
        }

        // If this is a landing_page command, make sure we're sending the topic as the command_prompt
        if (lastMessage.commandType === 'landing_page') {
          console.log('Debug - Processing landing page topic:', userMessage.content);
          requestBody.command_type = 'landing_page';
          requestBody.command_prompt = userMessage.content;
          // Keep needs_more_info as true to follow the API's expected flow
          requestBody.needs_more_info = true;
        }
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
            errorData.message?.includes('Expired token');

          setMessages((prev) => [
            ...prev,
            {
              content: "I've detected an authentication issue. Please click the 'Refresh Connection' button below to reconnect to WordPress.",
              role: 'assistant',
              timestamp: Date.now(),
              id: generateUniqueId(),
            },
          ]);

          if (isExpiredToken) {
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

      // Check if this was a command response
      const isCommand = data.command_detected || data.command_executed;
      const needsConfirmation = data.needs_confirmation === true;
      const needsMoreInfo = data.needs_more_info === true;

      // If a section was added, handle it with our special function
      if (data.section_added) {
        handleSectionAdded(data);
        return; // Skip adding the regular message
      }

      const assistantMessage: Message = {
        content: data.content || data.response || 'Sorry, I could not generate a response.',
        role: 'assistant',
        timestamp: Date.now(),
        id: data.id,
        links: data.links,
        isCommand: !!isCommand,
        commandExecuted: data.command_executed,
        commandFailed: data.command_failed,
        needsConfirmation: needsConfirmation,
        commandType: data.command_type,
        commandPrompt: data.command_prompt,
        section_type: data.section_type,
        needs_more_info: needsMoreInfo,
      };

      setMessages((prev) => [...prev, assistantMessage]);

      // Check if a section was added and handle it separately
      if (data.section_added) {
        handleSectionAdded(data);
      }

      // If the command was confirmed but we need more info (like for landing page topic)
      // and the command type is landing_page, we need to ask for the topic
      if (lastMessage.commandType === 'landing_page' && data.command_type === 'landing_page' &&
        !data.command_executed && !data.needs_confirmation && !data.needs_more_info) {
        // Add a message asking for the topic
        const topicMessage: Message = {
          content: "I'd be happy to create a landing page for you. What topic or business would you like it to be about?",
          role: 'assistant',
          timestamp: Date.now(),
          id: generateUniqueId(),
          isCommand: true,
          commandType: 'landing_page',
          needs_more_info: true,
        };
        setMessages((prev) => [...prev, topicMessage]);
      }
    } catch (error) {
      console.error('Chat error:', error);

      // Handle errors from the API
      let errorMessage: Message;
      const errorString = error instanceof Error ? error.message : String(error);

      if (errorString.includes('Missing WordPress URL')) {
        errorMessage = {
          content: 'Please provide your WordPress site URL to continue.',
          role: 'assistant',
          timestamp: Date.now(),
          id: generateUniqueId(),
        };
      } else if (errorString.includes('Authentication required')) {
        errorMessage = {
          content: errorString,
          role: 'assistant',
          timestamp: Date.now(),
          id: generateUniqueId(),
        };
        setAuthError(true);
      } else {
        errorMessage = {
          content: `Sorry, I encountered an error: ${errorString}`,
          role: 'assistant',
          timestamp: Date.now(),
          id: generateUniqueId(),
        };
      }

      setMessages((prev) => [...prev, errorMessage]);
    } finally {
      setIsLoading(false);
    }
  };

  // Handle link clicks
  const handleLinkClick = (e: React.MouseEvent<HTMLAnchorElement>, link: { text: string; url: string; command?: string }) => {
    e.preventDefault();

    // Special handling for refresh and view links
    if (link.text.includes('Refresh')) {
      // Refresh the current page
      window.location.href = link.url;
      return;
    } else if (link.text.includes('View')) {
      // Open in new tab
      window.open(link.url, '_blank');
      return;
    }

    // Handle section links (starts with #add-)
    if (link.url.startsWith('#add-')) {
      // Extract the section type from the URL
      const sectionType = link.url.replace('#add-', '');

      // Convert section type to the format expected by the API
      let apiSectionType = sectionType;

      // Handle special cases for URL-based section types
      if (sectionType === 'side-by-side') {
        apiSectionType = 'side_by_side';
      } else if (sectionType === 'card-group') {
        apiSectionType = 'card_group';
      }

      // Get the current page ID if we're on a landing page
      const pageElement = document.querySelector('[data-post-type="landing"]');
      let pageId = null;

      if (pageElement) {
        pageId = pageElement.getAttribute('data-post-id');
      }

      // Add a user message showing what they clicked
      const userMessage: Message = {
        content: link.text.includes('Landing') ? 'add landing' : `add ${sectionType} section`,
        role: 'user',
        timestamp: Date.now(),
        id: generateUniqueId(),
      };
      setMessages((prev) => [...prev, userMessage]);

      // For landing page creation - directly ask for a topic
      if (sectionType === 'landing') {
        // Add a message asking for a topic
        const topicMessage: Message = {
          content: "I'd be happy to create a landing page for you. What topic or business would you like it to be about?",
          role: 'assistant',
          timestamp: Date.now(),
          id: generateUniqueId(),
          isCommand: true,
          commandType: 'landing_page',
          needs_more_info: true,
        };
        setMessages((prev) => [...prev, topicMessage]);
      }
      // For section addition
      else {
        // Add a system message asking for a description
        const systemMessage: Message = {
          content: `I'd be happy to add a ${sectionType} section to this landing page. What would you like this section to be about? Please provide a brief description or topic.`,
          role: 'assistant',
          timestamp: Date.now(),
          id: generateUniqueId(),
          isCommand: true,
          commandType: 'add_section',
          section_type: apiSectionType,
          needs_more_info: true,
          page_id: pageId ? parseInt(pageId, 10) : undefined,
        };
        setMessages((prev) => [...prev, systemMessage]);
      }

      // Focus the input field
      setTimeout(() => {
        if (inputRef.current) {
          inputRef.current.focus();
        }
      }, 100);

      return;
    }

    // Default behavior - open the link in a new tab
    window.open(link.url, '_blank');
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
          <span>PressX ChatBot</span>
        </button>
      ) : (
        <div className="w-[450px] bg-white rounded-xl shadow-2xl overflow-hidden flex flex-col min-h-[400px] max-h-[600px] border border-gray-200">
          <div className="bg-gray-100 p-4 flex justify-between items-center border-b border-gray-200">
            <div className="flex items-center gap-2">
              <Bot className="w-5 h-5 text-primary" />
              <h3 className="text-lg font-semibold text-gray-800">PressX ChatBot</h3>
            </div>
            <button
              onClick={() => setIsOpen(false)}
              className="text-gray-500 hover:text-gray-700 p-1 hover:bg-gray-200 rounded transition-colors duration-200"
              aria-label="Close chat"
            >
              <span className="text-xl">✕</span>
            </button>
          </div>
          <div className="flex-1 overflow-y-auto p-4 space-y-4 min-h-0 bg-white chat-messages">
            {messages.map((message) => (
              <div
                key={message.id || `${message.timestamp}-${Math.random().toString(36).substr(2, 9)}`}
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
                  <div className="whitespace-pre-wrap">{message.content}</div>

                  {/* Links */}
                  {message.links && message.links.length > 0 && (
                    <div className="mt-2 pt-2 border-t border-gray-200">
                      <div className="flex flex-wrap gap-2">
                        {message.links.map((link, index) => (
                          <a
                            key={index}
                            href={link.url}
                            onClick={(e) => handleLinkClick(e, link)}
                            className={`text-sm font-semibold flex items-center gap-1.5 px-3 py-1.5 rounded-md transition-colors ${link.text.includes('Refresh')
                              ? 'text-white bg-primary hover:bg-primary/90'
                              : link.text.includes('View')
                                ? 'text-primary hover:text-primary/80 bg-primary/10 hover:bg-primary/20'
                                : 'text-primary hover:text-primary/80 bg-primary/10 hover:bg-primary/20'
                              }`}
                          >
                            {link.text}
                          </a>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Confirmation UI */}
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
                              id: generateUniqueId(),
                            };
                            setMessages((prev) => [...prev, userMessage]);
                            setIsLoading(true);
                            setAuthError(false);

                            // Prepare the request body with confirmation parameters
                            const requestBody: ConfirmationRequestBody = {
                              messages: [{ role: 'user', content: 'yes' }],
                              confirmed: 'yes',
                              command_type: message.commandType,
                              command_prompt: message.commandPrompt,
                              message: 'yes',
                            };

                            // If this is an add_section command, include the section_type and page_id
                            if (message.commandType === 'add_section') {
                              // Ensure section_type is in the correct format
                              let apiSectionType = message.section_type;

                              // Handle special cases
                              if (message.section_type === 'side-by-side') {
                                apiSectionType = 'side_by_side';
                              } else if (message.section_type === 'card-group') {
                                apiSectionType = 'card_group';
                              }

                              requestBody.section_type = apiSectionType;

                              // Include the page_id from the message if it exists
                              if (message.page_id) {
                                requestBody.page_id = message.page_id;
                                console.log('Debug - Including page ID in confirmation:', message.page_id);
                              } else {
                                // Fallback: try to get the current page ID if we're on a landing page
                                const pageElement = document.querySelector('[data-post-type="landing"]');
                                if (pageElement) {
                                  const pageId = pageElement.getAttribute('data-post-id');
                                  if (pageId) {
                                    requestBody.page_id = parseInt(pageId, 10);
                                    console.log('Debug - Found page ID for confirmation:', requestBody.page_id);
                                  }
                                }
                              }
                            }
                            // If this is a landing_page command, ensure we're sending the right parameters
                            else if (message.commandType === 'landing_page') {
                              console.log('Debug - Processing landing page confirmation');
                              // Make sure we're sending the command_type
                              requestBody.command_type = 'landing_page';

                              // Include the command_prompt (topic) that was confirmed
                              if (message.commandPrompt) {
                                requestBody.command_prompt = message.commandPrompt;
                                console.log('Debug - Creating landing page with topic:', message.commandPrompt);
                              }

                              // Set confirmed to yes to tell the API to create the landing page
                              requestBody.confirmed = 'yes';
                            }

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

                                    // Check if this is a token expiration issue
                                    const isExpiredToken =
                                      errorData.raw_error?.message?.includes('Expired token') ||
                                      errorData.message?.includes('Expired token');

                                    if (isExpiredToken) {
                                      setMessages((prev) => [
                                        ...prev,
                                        {
                                          content: "I've detected an authentication issue. Please click the 'Refresh Connection' button below to reconnect to WordPress.",
                                          role: 'assistant',
                                          timestamp: Date.now(),
                                          id: generateUniqueId(),
                                        },
                                      ]);
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
                                const needsMoreInfo = data.needs_more_info === true;

                                // Create the assistant message from the API response
                                const assistantMessage: Message = {
                                  content: data.content || data.response || 'Sorry, I could not generate a response.',
                                  role: 'assistant',
                                  timestamp: Date.now(),
                                  id: data.id,
                                  links: data.links,
                                  isCommand: !!isCommand,
                                  commandExecuted: data.command_executed,
                                  commandFailed: data.command_failed,
                                  needsConfirmation: needsConfirmation,
                                  commandType: data.command_type,
                                  commandPrompt: data.command_prompt,
                                  section_type: data.section_type,
                                  needs_more_info: needsMoreInfo,
                                };

                                // Add the assistant message to the chat
                                setMessages((prev) => [...prev, assistantMessage]);

                                // Check if a section was added and handle it separately
                                if (data.section_added) {
                                  handleSectionAdded(data);
                                }

                                // If this was a landing page confirmation and the API didn't ask for more info,
                                // explicitly add a topic request message
                                if (message.commandType === 'landing_page' &&
                                  !data.command_executed && !data.needs_confirmation && !data.needs_more_info) {
                                  console.log('Debug - Adding explicit topic request after landing page confirmation');

                                  // Add a slight delay to make the conversation flow more natural
                                  setTimeout(() => {
                                    const topicMessage: Message = {
                                      content: "What topic or business would you like the landing page to be about?",
                                      role: 'assistant',
                                      timestamp: Date.now(),
                                      id: generateUniqueId(),
                                      isCommand: true,
                                      commandType: 'landing_page',
                                      needs_more_info: true,
                                    };
                                    setMessages((prev) => [...prev, topicMessage]);
                                  }, 500);
                                }
                              })
                              .catch((error) => {
                                console.error('Chat error:', error);

                                // Handle errors from the API
                                let errorMessage: Message;
                                const errorString = error instanceof Error ? error.message : String(error);

                                if (errorString.includes('Missing WordPress URL')) {
                                  errorMessage = {
                                    content: 'The chat feature is not properly configured. The WordPress URL is missing. Please contact the site administrator.',
                                    role: 'assistant',
                                    timestamp: Date.now(),
                                    id: generateUniqueId(),
                                  };
                                } else if (errorString.includes('Expired token')) {
                                  // Set auth error to true to display the refresh token button
                                  setAuthError(true);
                                  errorMessage = {
                                    content: 'Your authentication token has expired. Please look for the "Refresh Token" button in the chat window and click it to get a new token.',
                                    role: 'assistant',
                                    timestamp: Date.now(),
                                    id: generateUniqueId(),
                                  };
                                } else {
                                  errorMessage = {
                                    content: error instanceof Error ? error.message : 'Sorry, I encountered an error. Please try again.',
                                    role: 'assistant',
                                    timestamp: Date.now(),
                                    id: generateUniqueId(),
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
                              id: generateUniqueId(),
                            };
                            setMessages((prev) => [...prev, userMessage]);
                            setIsLoading(true);
                            setAuthError(false);

                            // Prepare the request body with confirmation parameters
                            const requestBody: ConfirmationRequestBody = {
                              messages: [{ role: 'user', content: 'no' }],
                              confirmed: 'no',
                              command_type: message.commandType,
                              command_prompt: message.commandPrompt,
                              message: 'no',
                            };

                            // If this is an add_section command, include the section_type and page_id
                            if (message.commandType === 'add_section') {
                              // Ensure section_type is in the correct format
                              let apiSectionType = message.section_type;

                              // Handle special cases
                              if (message.section_type === 'side-by-side') {
                                apiSectionType = 'side_by_side';
                              } else if (message.section_type === 'card-group') {
                                apiSectionType = 'card_group';
                              }

                              requestBody.section_type = apiSectionType;

                              // Include the page_id from the message if it exists
                              if (message.page_id) {
                                requestBody.page_id = message.page_id;
                                console.log('Debug - Including page ID in no confirmation:', message.page_id);
                              } else {
                                // Fallback: try to get the current page ID if we're on a landing page
                                const pageElement = document.querySelector('[data-post-type="landing"]');
                                if (pageElement) {
                                  const pageId = pageElement.getAttribute('data-post-id');
                                  if (pageId) {
                                    requestBody.page_id = parseInt(pageId, 10);
                                    console.log('Debug - Found page ID for no confirmation:', requestBody.page_id);
                                  }
                                }
                              }
                            }

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

                                    // Check if this is a token expiration issue
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
                                const needsMoreInfo = data.needs_more_info === true;

                                // Create the assistant message from the API response
                                const assistantMessage: Message = {
                                  content: data.content || data.response || 'Sorry, I could not generate a response.',
                                  role: 'assistant',
                                  timestamp: Date.now(),
                                  id: data.id,
                                  links: data.links,
                                  isCommand: !!isCommand,
                                  commandExecuted: data.command_executed,
                                  commandFailed: data.command_failed,
                                  needsConfirmation: needsConfirmation,
                                  commandType: data.command_type,
                                  commandPrompt: data.command_prompt,
                                  section_type: data.section_type,
                                  needs_more_info: needsMoreInfo,
                                };

                                // Add the assistant message to the chat
                                setMessages((prev) => [...prev, assistantMessage]);
                              })
                              .catch((error) => {
                                console.error('Chat error:', error);

                                // Handle errors from the API
                                let errorMessage: Message;
                                const errorString = error instanceof Error ? error.message : String(error);

                                if (errorString.includes('Missing WordPress URL')) {
                                  errorMessage = {
                                    content: 'The chat feature is not properly configured. The WordPress URL is missing. Please contact the site administrator.',
                                    role: 'assistant',
                                    timestamp: Date.now(),
                                    id: generateUniqueId(),
                                  };
                                } else if (errorString.includes('Expired token')) {
                                  // Set auth error to true to display the refresh token button
                                  setAuthError(true);
                                  errorMessage = {
                                    content: 'Your authentication token has expired. Please look for the "Refresh Token" button in the chat window and click it to get a new token.',
                                    role: 'assistant',
                                    timestamp: Date.now(),
                                    id: generateUniqueId(),
                                  };
                                } else {
                                  errorMessage = {
                                    content: error instanceof Error ? error.message : 'Sorry, I encountered an error. Please try again.',
                                    role: 'assistant',
                                    timestamp: Date.now(),
                                    id: generateUniqueId(),
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

                  {/* Command execution status */}
                  {message.commandExecuted && (
                    <div className="mt-2 text-xs text-gray-500">
                      {message.commandFailed
                        ? '❌ Command failed'
                        : '✅ Command executed successfully'}
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
            <div ref={messagesEndRef} />
          </div>
          <form onSubmit={handleSubmit} className="p-4 border-t border-gray-200 bg-white">
            <div className="flex space-x-2">
              <input
                type="text"
                value={input}
                onChange={(e) => setInput(e.target.value)}
                placeholder="Type your message or try 'create a landing page for...'"
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
                Authentication error. Please try refreshing your token.
              </div>
            )}
          </form>
        </div>
      )}
    </div>
  );
}
