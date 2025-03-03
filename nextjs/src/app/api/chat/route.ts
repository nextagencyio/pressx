import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { revalidatePath } from 'next/cache';

// Parse cookies from header string
function parseCookies(cookieHeader: string) {
  const list: Record<string, string> = {};
  if (!cookieHeader) return list;

  cookieHeader.split(';').forEach(cookie => {
    const [name, ...rest] = cookie.split('=');
    const trimmedName = name?.trim();
    if (!trimmedName) return;
    const value = rest.join('=').trim();
    if (!value) return;
    list[trimmedName] = decodeURIComponent(value);
  });

  return list;
}

export async function POST(request: NextRequest) {
  try {
    // Parse request body
    const req = await request.json();
    const { messages, confirmed, command_type, command_prompt, needs_more_info, section_type, page_id } = req;

    // Check if preview mode is enabled
    const previewMode = process.env.NEXT_PUBLIC_PREVIEW_MODE === 'true';
    if (!previewMode) {
      return NextResponse.json(
        { error: 'Preview mode is not enabled' },
        { status: 403 }
      );
    }

    // Validate request
    if (!messages || !Array.isArray(messages) || messages.length === 0) {
      return NextResponse.json(
        { error: 'Invalid request. Expected messages array.' },
        { status: 400 }
      );
    }

    // Get the latest user message
    const latestUserMessage = messages
      .filter((msg: any) => msg.role === 'user')
      .pop();

    if (!latestUserMessage) {
      return NextResponse.json(
        { error: 'No user message found' },
        { status: 400 }
      );
    }

    // Get WordPress URL from environment variable
    const wpUrl = process.env.NEXT_PUBLIC_WORDPRESS_URL;
    if (!wpUrl) {
      return NextResponse.json(
        {
          error: 'WordPress URL not configured',
          wp_url: null,
          debug_info: 'NEXT_PUBLIC_WORDPRESS_URL environment variable is not set',
        },
        { status: 500 }
      );
    }

    // Get JWT token from cookies
    const cookieHeader = request.headers.get('cookie') || '';
    const parsedCookies = parseCookies(cookieHeader);
    const jwtToken = parsedCookies['pressxJWT'];

    // Always require authentication in preview mode
    if (!jwtToken) {
      return NextResponse.json(
        {
          error: 'Authentication required',
          message: 'No authentication token found. Please log in.',
          wp_url: wpUrl,
          debug_info: 'No JWT token found in cookies',
          token_value: 'none',
        },
        { status: 401 }
      );
    }

    try {
      // Send request to WordPress API
      console.log('Sending request to WordPress API:', `${wpUrl}/wp-json/pressx/v1/chat`);

      // Prepare the request body
      const requestBody: any = {
        message: latestUserMessage.content,
      };

      // Add confirmation parameters if they exist
      if (confirmed) {
        requestBody.confirmed = confirmed;
        // If this is a confirmation, use the confirmation as the message
        requestBody.message = confirmed;
      }

      if (command_type) {
        requestBody.command_type = command_type;
      }

      if (command_prompt) {
        requestBody.command_prompt = command_prompt;
      }

      if (needs_more_info) {
        requestBody.needs_more_info = needs_more_info;
      }

      // Add section_type if it exists
      if (section_type) {
        requestBody.section_type = section_type;
      }

      // Add page_id if it exists
      if (page_id) {
        requestBody.page_id = page_id;
        console.log('Debug - Sending page_id to WordPress API:', page_id);
      }

      const response = await fetch(`${wpUrl}/wp-json/pressx/v1/chat`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          // Always include Authorization header if token exists, regardless of requireAuth setting
          ...(jwtToken && { 'Authorization': `Bearer ${jwtToken}` }),
        },
        cache: 'no-store',  // Prevent caching
        body: JSON.stringify(requestBody),
      });

      // Handle response
      if (!response.ok) {
        const errorText = await response.text();
        console.error('WordPress API error response:', errorText);
        let errorData;

        try {
          errorData = JSON.parse(errorText);
        } catch (e) {
          errorData = { message: errorText };
        }

        // Handle authentication errors
        if (response.status === 401) {
          // Check specifically for expired token
          const isExpiredToken =
            errorData.message?.includes('Expired token') ||
            errorText.includes('Expired token');

          const errorMessage = isExpiredToken
            ? 'Your authentication token has expired. Please refresh your token.'
            : 'WordPress API authentication failed. Please log in again.';

          return NextResponse.json(
            {
              error: 'WordPress API authentication failed',
              message: errorMessage,
              expired_token: isExpiredToken,
              wp_url: wpUrl,
              debug_info: 'JWT token was sent but rejected',
              token_value: jwtToken ? `${jwtToken.substring(0, 10)}...` : 'none',
              raw_error: errorData,
            },
            { status: 401 }
          );
        }

        return NextResponse.json(
          {
            error: 'WordPress API error',
            message: errorData.message || 'Unknown error',
            wp_url: wpUrl,
            debug_info: 'API request failed with status ' + response.status,
            raw_error: errorData,
          },
          { status: response.status }
        );
      }

      const data = await response.json();
      console.log('WordPress API response data:', data);

      // Debug section data for instant preview
      if (data.section_added) {
        console.log('Section added response:', {
          section_added: data.section_added,
          dev_mode: data.dev_mode,
          section_data: data.section_data
        });
      }

      // Prepare the response
      const responseData = {
        content: data.response,
        response: data.response,
        command_detected: data.command_detected || false,
        command_executed: data.command_executed || false,
        command_failed: data.command_failed || false,
        needs_more_info: data.needs_more_info || false,
        needs_confirmation: data.needs_confirmation || false,
        command_type: data.command_type || null,
        command_prompt: data.command_prompt || null,
        section_type: data.section_type || null,
        links: data.links || [],
        section_added: data.section_added || false,
        dev_mode: data.dev_mode || false,
        section_data: data.section_data || null,
        revalidated: false,
      };

      // If a section was added and we have section_data with a page_url, add a cache busting parameter
      if (data.section_added && data.section_data && data.section_data.page_url) {
        try {
          // Add a timestamp to the page_url to force a cache bust
          const timestamp = Date.now();
          const url = new URL(data.section_data.page_url);
          url.searchParams.set('t', timestamp.toString());
          data.section_data.page_url = url.toString();
          console.log('Updated page URL with cache bust:', data.section_data.page_url);

          // We're not using revalidatePath anymore since we're handling refresh via UI buttons
          responseData.revalidated = false;
        } catch (error) {
          console.error('Error updating page URL:', error);
        }
      }

      return NextResponse.json(responseData);
    } catch (error) {
      console.error('Error calling WordPress API:', error);
      return NextResponse.json(
        {
          error: 'Failed to communicate with WordPress API',
          message: error instanceof Error ? error.message : 'Unknown error',
          wp_url: wpUrl,
          debug_info: 'Exception caught in API route',
          error_type: error instanceof Error ? error.name : typeof error,
        },
        { status: 500 }
      );
    }
  } catch (error) {
    console.error('Error processing request:', error);
    return NextResponse.json(
      { error: 'Internal server error' },
      { status: 500 }
    );
  }
}
