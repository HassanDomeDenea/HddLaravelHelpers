---
name: using-whatsapp
description: WhatsApp phone number verification using the WhatsappClient service.
---

# WhatsApp Integration

## When to use this skill
Use this skill when verifying phone numbers via WhatsApp, checking if a number has WhatsApp, or retrieving contact names.

## Environment Variables

Add these to your `.env` file:

```env
HDD_WHATSAPP_USER_ID=your-user-id
HDD_WHATSAPP_USERNAME=your-username
HDD_WHATSAPP_PASSWORD=your-password
HDD_WHATSAPP_HOSTNAME=whatsapp-bot.hdd-apps.com   # optional, this is the default
```

These map to config keys in `config/hdd-laravel-helpers.php`:

```php
'whatsapp-userid' => env('HDD_WHATSAPP_USER_ID'),
'whatsapp-username' => env('HDD_WHATSAPP_USERNAME'),
'whatsapp-password' => env('HDD_WHATSAPP_PASSWORD'),
'whatsapp-hostname' => env('HDD_WHATSAPP_HOSTNAME', 'whatsapp-bot.hdd-apps.com'),
```

## Usage

```php
use HassanDomeDenea\HddLaravelHelpers\Whatsapp\WhatsappClient;
use HassanDomeDenea\HddLaravelHelpers\Whatsapp\Responses\WhatsappApiCheckNumberResponse;
use HassanDomeDenea\HddLaravelHelpers\Whatsapp\Responses\WhatsappFailedResponse;

$client = new WhatsappClient();
$result = $client->checkNumber('+9647701234567');

if ($result instanceof WhatsappApiCheckNumberResponse) {
    // Success response
    $result->success;      // true (always)
    $result->hasWhatsapp;  // bool - whether the number has WhatsApp
    $result->contactName;  // string - WhatsApp display name
} elseif ($result instanceof WhatsappFailedResponse) {
    // Failed response
    $result->success;  // false (always)
    $result->message;  // string - error message
}
```

## Phone Number Normalization

The `checkNumber()` method automatically normalizes Iraqi phone numbers:

1. Strips `+` prefix
2. Strips leading `964` country code
3. Strips leading `0`
4. Prepends `964`

Examples:
- `+9647701234567` -> `9647701234567`
- `07701234567` -> `9647701234567`
- `7701234567` -> `9647701234567`

Numbers shorter than 12 digits after normalization return a `WhatsappFailedResponse` with "Invalid phone number".

## Response Types

### WhatsappApiCheckNumberResponse (extends Spatie Data)
- `success: bool` - Always `true`
- `hasWhatsapp: bool` - Whether the number is registered on WhatsApp
- `contactName: string` - The contact's WhatsApp display name

### WhatsappFailedResponse (extends Spatie Data)
- `success: bool` - Always `false`
- `message: ?string` - Error description

## Complete Example

```php
public function verifyWhatsapp(Request $request): JsonResponse
{
    $request->validate(['phone' => 'required|string']);

    $client = new WhatsappClient();
    $result = $client->checkNumber($request->phone);

    if ($result instanceof WhatsappFailedResponse) {
        return ApiResponse::failedResponse($result->message);
    }

    if (!$result->hasWhatsapp) {
        return ApiResponse::failedResponse('This number does not have WhatsApp');
    }

    return ApiResponse::successResponse([
        'has_whatsapp' => true,
        'contact_name' => $result->contactName,
    ]);
}
```
