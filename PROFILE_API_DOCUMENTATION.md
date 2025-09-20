# User Profile API Documentation

## Base URL

```
/api/v1/profile
```

All endpoints require authentication via Bearer token in the Authorization header.

## Authentication

```http
Authorization: Bearer {your_token}
```

---

## Profile Information

### Get Profile

**GET** `/api/v1/profile`

Returns complete user profile information including membership, referral stats, and account details.

**Response:**

```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "uuid": "550e8400-e29b-41d4-a716-446655440000",
            "name": "John Doe",
            "email": "john@example.com",
            "phone": "+1234567890",
            "referral_code": "ABC12345",
            "email_verified_at": "2025-01-15T10:30:00Z",
            "phone_verified_at": null,
            "total_points": 1500,
            "tasks_completed_today": 5,
            "last_task_reset_date": "2025-01-15",
            "created_at": "2025-01-01T00:00:00Z",
            "updated_at": "2025-01-15T10:30:00Z"
        },
        "membership": {
            "id": 1,
            "name": "Premium",
            "type": "premium",
            "benefits": ["Priority support", "Extra tasks"],
            "reward_multiplier": 1.5,
            "priority_level": 2,
            "purchased_at": "2025-01-01T00:00:00Z",
            "expires_at": "2025-12-31T23:59:59Z"
        },
        "referral_stats": {
            "total_referrals": 10,
            "active_referrals": 8,
            "pending_referrals": 2
        }
    }
}
```

### Update Profile

**PUT** `/api/v1/profile`

Update basic profile information (name, email, phone).

**Request Body:**

```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Profile updated successfully",
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "phone": "+1234567890",
            "email_verified_at": null,
            "phone_verified_at": null
        },
        "email_verification_required": true,
        "phone_verification_required": true
    }
}
```

### Update Password

**PUT** `/api/v1/profile/password`

Change user password.

**Request Body:**

```json
{
    "current_password": "oldpassword123",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Password updated successfully"
}
```

---

## Profile Picture Management

### Upload Profile Picture

**POST** `/api/v1/profile/picture`

Upload a new profile picture.

**Request:** Multipart form data

-   `profile_picture`: Image file (jpeg, png, jpg, gif, max 2MB)

**Response:**

```json
{
    "success": true,
    "message": "Profile picture updated successfully",
    "data": {
        "profile_picture_url": "https://yourapp.com/storage/profile-pictures/abc123.jpg"
    }
}
```

### Delete Profile Picture

**DELETE** `/api/v1/profile/picture`

Remove current profile picture.

**Response:**

```json
{
    "success": true,
    "message": "Profile picture deleted successfully"
}
```

---

## Activity & Statistics

### Get Activity History

**GET** `/api/v1/profile/activity?page=1&limit=20`

Get user's activity history including task completions, membership changes, and referrals.

**Query Parameters:**

-   `page` (optional): Page number (default: 1)
-   `limit` (optional): Items per page (default: 20)

**Response:**

```json
{
    "success": true,
    "data": {
        "task_history": {
            "data": [
                {
                    "id": 1,
                    "task_id": 5,
                    "status": "completed",
                    "completed_at": "2025-01-15T10:30:00Z",
                    "points_earned": 50,
                    "task": {
                        "id": 5,
                        "title": "Follow Instagram Account",
                        "description": "Follow our Instagram account"
                    }
                }
            ],
            "pagination": {
                "current_page": 1,
                "last_page": 5,
                "per_page": 20,
                "total": 100
            }
        },
        "membership_history": [
            {
                "id": 1,
                "membership": {
                    "name": "Premium",
                    "type": "premium"
                },
                "created_at": "2025-01-01T00:00:00Z",
                "expires_at": "2025-12-31T23:59:59Z"
            }
        ],
        "referral_history": [
            {
                "id": 1,
                "referred_user": {
                    "name": "Jane Doe",
                    "email": "jane@example.com"
                },
                "status": "active",
                "created_at": "2025-01-10T00:00:00Z"
            }
        ]
    }
}
```

### Get Statistics

**GET** `/api/v1/profile/stats`

Get user's statistics and achievements.

**Response:**

```json
{
    "success": true,
    "data": {
        "total_tasks_completed": 150,
        "total_points_earned": 1500,
        "current_streak": 7,
        "longest_streak": 30,
        "recent_activity": 5,
        "referral_stats": {
            "total_referrals": 10,
            "active_referrals": 8,
            "pending_referrals": 2
        },
        "membership_level": "Premium",
        "rank": "Gold"
    }
}
```

---

## Referral Management

### Get Referral Information

**GET** `/api/v1/profile/referrals`

Get user's referral code and statistics.

**Response:**

```json
{
    "success": true,
    "data": {
        "referral_code": "ABC12345",
        "referral_url": "https://yourapp.com/register?ref=ABC12345",
        "stats": {
            "total_referrals": 10,
            "active_referrals": 8,
            "pending_referrals": 2
        },
        "recent_referrals": [
            {
                "id": 1,
                "referred_user": {
                    "name": "Jane Doe",
                    "email": "jane@example.com"
                },
                "status": "active",
                "created_at": "2025-01-10T00:00:00Z"
            }
        ]
    }
}
```

---

## Privacy Settings

### Get Privacy Settings

**GET** `/api/v1/profile/privacy`

Get current privacy settings.

**Response:**

```json
{
    "success": true,
    "data": {
        "profile_visibility": "public",
        "show_email": false,
        "show_phone": false,
        "show_activity": true,
        "email_notifications": true,
        "sms_notifications": false
    }
}
```

### Update Privacy Settings

**PUT** `/api/v1/profile/privacy`

Update privacy and notification settings.

**Request Body:**

```json
{
    "profile_visibility": "friends",
    "show_email": true,
    "show_phone": false,
    "show_activity": true,
    "email_notifications": true,
    "sms_notifications": true
}
```

**Response:**

```json
{
    "success": true,
    "message": "Privacy settings updated successfully",
    "data": {
        "profile_visibility": "friends",
        "show_email": true,
        "show_phone": false,
        "show_activity": true,
        "email_notifications": true,
        "sms_notifications": true
    }
}
```

---

## Account Management

### Deactivate Account

**POST** `/api/v1/profile/deactivate`

Deactivate user account (requires password confirmation).

**Request Body:**

```json
{
    "password": "userpassword123",
    "reason": "No longer using the service"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Account deactivated successfully"
}
```

---

## Error Responses

All endpoints may return the following error responses:

### 401 Unauthorized

```json
{
    "message": "Unauthenticated."
}
```

### 422 Validation Error

```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."]
    }
}
```

### 500 Server Error

```json
{
    "success": false,
    "message": "Internal server error"
}
```

---

## Notes

1. **File Upload**: Profile pictures are stored in `storage/app/public/profile-pictures/`
2. **Rate Limiting**: Some endpoints may have rate limiting applied
3. **Email Verification**: When email is changed, a new verification code is sent
4. **Phone Verification**: When phone is changed, it needs to be re-verified
5. **Account Deactivation**: Deactivated accounts cannot log in and all tokens are revoked

