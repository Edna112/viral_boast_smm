<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailNotificationService
{
    /**
     * Send payment received notification
     */
    public function sendPaymentNotification(User $user, array $paymentData): bool
    {
        try {
            $amount = $paymentData['amount'] ?? 0;
            $currency = $paymentData['currency'] ?? 'USD';
            $transactionId = $paymentData['transaction_id'] ?? 'N/A';
            $paymentMethod = $paymentData['payment_method'] ?? 'Unknown';
            $balance = $paymentData['balance'] ?? 0;
            $date = now()->format('Y-m-d H:i:s');

            $subject = 'Payment Received - viralboast';
            $message = "Hello {$user->name},\n\n";
            $message .= "Your payment has been successfully received.\n\n";
            $message .= "Payment Details:\n";
            $message .= "- Amount: {$currency} " . number_format($amount, 2) . "\n";
            $message .= "- Transaction ID: {$transactionId}\n";
            $message .= "- Payment Method: {$paymentMethod}\n";
            $message .= "- Date: {$date}\n";
            $message .= "- Current Balance: {$currency} " . number_format($balance, 2) . "\n\n";
            $message .= "Login to check your balance here: https://viralboast.com/login\n\n";
            $message .= "Thank you for using viralboast!\n\n";
            $message .= "This is an automated message. Please do not reply to this email.";

            Mail::raw($message, function ($mail) use ($user, $subject) {
                $mail->to($user->email, $user->name)
                     ->subject($subject)
                     ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info('Payment notification email sent', [
                'user_email' => $user->email,
                'amount' => $amount,
                'currency' => $currency
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send payment notification email', [
                'user_email' => $user->email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send withdrawal notification
     */
    public function sendWithdrawalNotification(User $user, array $withdrawalData): bool
    {
        try {
            $amount = $withdrawalData['amount'] ?? 0;
            $currency = $withdrawalData['currency'] ?? 'USD';
            $transactionId = $withdrawalData['transaction_id'] ?? 'N/A';
            $withdrawalMethod = $withdrawalData['withdrawal_method'] ?? 'Unknown';
            $accountDetails = $withdrawalData['account_details'] ?? null;
            $walletAddress = $withdrawalData['wallet_address'] ?? null;
            $addressType = $withdrawalData['address_type'] ?? null;
            $status = $withdrawalData['status'] ?? 'pending';
            $balance = $withdrawalData['balance'] ?? 0;
            $date = now()->format('Y-m-d H:i:s');

            $subject = 'Withdrawal ' . ucfirst($status) . ' - PIS';
            
            $message = "Hello {$user->name},\n\n";
            $message .= "Your withdrawal request has been {$status}.\n\n";
            $message .= "Withdrawal Details:\n";
            $message .= "- Amount: {$currency} " . number_format($amount, 2) . "\n";
            $message .= "- Transaction ID: {$transactionId}\n";
            $message .= "- Withdrawal Method: {$withdrawalMethod}\n";
            if ($accountDetails) {
                $message .= "- Account Details: {$accountDetails}\n";
            }
            if ($walletAddress) {
                $message .= "- Wallet Address: {$walletAddress}\n";
            }
            if ($addressType) {
                $message .= "- Address Type: {$addressType}\n";
            }
            $message .= "- Status: " . ucfirst($status) . "\n";
            $message .= "- Date: {$date}\n";
            $message .= "- Current Balance: {$currency} " . number_format($balance, 2) . "\n\n";
            
            if ($status === 'pending') {
                $message .= "Your withdrawal request is being reviewed by our team. You will receive another notification once it's processed.\n\n";
            } elseif ($status === 'approved') {
                $message .= "Your withdrawal has been approved and the funds will be transferred to your account shortly.\n\n";
            } elseif ($status === 'rejected') {
                $message .= "Unfortunately, your withdrawal request could not be processed. Please contact support for more information.\n\n";
            }

            $message .= "Login to check your balance here: https://viralboast.com/login\n\n";
            $message .= "Thank you for using viralboast!\n\n";
            $message .= "This is an automated message. Please do not reply to this email.";

            Mail::raw($message, function ($mail) use ($user, $subject) {
                $mail->to($user->email, $user->name)
                     ->subject($subject)
                     ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info('Withdrawal notification email sent', [
                'user_email' => $user->email,
                'amount' => $amount,
                'status' => $status
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send withdrawal notification email', [
                'user_email' => $user->email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send payment approved notification
     */
    public function sendPaymentApprovedNotification(User $user, array $paymentData): bool
    {
        try {
            $amount = $paymentData['amount'] ?? 0;
            $currency = $paymentData['currency'] ?? 'USD';
            $transactionId = $paymentData['transaction_id'] ?? 'N/A';
            $balance = $paymentData['balance'] ?? 0;
            $date = now()->format('Y-m-d H:i:s');

            $subject = 'Payment Approved - PIS';
            $message = "Hello {$user->name},\n\n";
            $message .= "Great news! Your payment has been reviewed and approved.\n\n";
            $message .= "Payment Details:\n";
            $message .= "- Amount: {$currency} " . number_format($amount, 2) . "\n";
            $message .= "- Transaction ID: {$transactionId}\n";
            $message .= "- Approval Date: {$date}\n";
            $message .= "- Current Balance: {$currency} " . number_format($balance, 2) . "\n\n";
            $message .= "Your account balance has been updated and the funds are now available for use.\n\n";
            $message .= "Login to check your balance here: https://viralboast.com/login\n\n";
            $message .= "Thank you for using viralboast!\n\n";
            $message .= "This is an automated message. Please do not reply to this email.";

            Mail::raw($message, function ($mail) use ($user, $subject) {
                $mail->to($user->email, $user->name)
                     ->subject($subject)
                     ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info('Payment approved notification email sent', [
                'user_email' => $user->email,
                'amount' => $amount
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send payment approved notification email', [
                'user_email' => $user->email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send withdrawal approved notification
     */
    public function sendWithdrawalApprovedNotification(User $user, array $withdrawalData): bool
    {
        try {
            $amount = $withdrawalData['amount'] ?? 0;
            $currency = $withdrawalData['currency'] ?? 'USD';
            $transactionId = $withdrawalData['transaction_id'] ?? 'N/A';
            $withdrawalMethod = $withdrawalData['withdrawal_method'] ?? 'Unknown';
            $accountDetails = $withdrawalData['account_details'] ?? null;
            $walletAddress = $withdrawalData['wallet_address'] ?? null;
            $addressType = $withdrawalData['address_type'] ?? null;
            $balance = $withdrawalData['balance'] ?? 0;
            $date = now()->format('Y-m-d H:i:s');

            $subject = 'Withdrawal Approved - viralboast';
            
            $message = "Hello {$user->name},\n\n";
            $message .= "Great news! Your withdrawal request has been approved.\n\n";
            $message .= "Withdrawal Details:\n";
            $message .= "- Amount: {$currency} " . number_format($amount, 2) . "\n";
            $message .= "- Transaction ID: {$transactionId}\n";
            $message .= "- Withdrawal Method: {$withdrawalMethod}\n";
            if ($accountDetails) {
                $message .= "- Account Details: {$accountDetails}\n";
            }
            if ($walletAddress) {
                $message .= "- Wallet Address: {$walletAddress}\n";
            }
            if ($addressType) {
                $message .= "- Address Type: {$addressType}\n";
            }
            $message .= "- Approval Date: {$date}\n";
            $message .= "- Current Balance: {$currency} " . number_format($balance, 2) . "\n\n";
            $message .= "The funds have been deducted from your account and will be transferred to your specified withdrawal method shortly.\n\n";
            $message .= "Login to check your balance here: https://viralboast.com/login\n\n";
            $message .= "Thank you for using viralboast!\n\n";
            $message .= "This is an automated message. Please do not reply to this email.";

            Mail::raw($message, function ($mail) use ($user, $subject) {
                $mail->to($user->email, $user->name)
                     ->subject($subject)
                     ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info('Withdrawal approved notification email sent', [
                'user_email' => $user->email,
                'amount' => $amount
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send withdrawal approved notification email', [
                'user_email' => $user->email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send withdrawal rejected notification
     */
    public function sendWithdrawalRejectedNotification(User $user, array $withdrawalData): bool
    {
        try {
            $amount = $withdrawalData['amount'] ?? 0;
            $currency = $withdrawalData['currency'] ?? 'USD';
            $transactionId = $withdrawalData['transaction_id'] ?? 'N/A';
            $withdrawalMethod = $withdrawalData['withdrawal_method'] ?? 'Unknown';
            $accountDetails = $withdrawalData['account_details'] ?? null;
            $walletAddress = $withdrawalData['wallet_address'] ?? null;
            $addressType = $withdrawalData['address_type'] ?? null;
            $reason = $withdrawalData['reason'] ?? 'No reason provided';
            $balance = $withdrawalData['balance'] ?? 0;
            $date = now()->format('Y-m-d H:i:s');

            $subject = 'Withdrawal Rejected - viralboast';
            
            $message = "Hello {$user->name},\n\n";
            $message .= "Unfortunately, your withdrawal request has been rejected.\n\n";
            $message .= "Withdrawal Details:\n";
            $message .= "- Amount: {$currency} " . number_format($amount, 2) . "\n";
            $message .= "- Transaction ID: {$transactionId}\n";
            $message .= "- Withdrawal Method: {$withdrawalMethod}\n";
            if ($accountDetails) {
                $message .= "- Account Details: {$accountDetails}\n";
            }
            if ($walletAddress) {
                $message .= "- Wallet Address: {$walletAddress}\n";
            }
            if ($addressType) {
                $message .= "- Address Type: {$addressType}\n";
            }
            $message .= "- Rejection Date: {$date}\n";
            $message .= "- Current Balance: {$currency} " . number_format($balance, 2) . "\n";
            $message .= "- Reason: {$reason}\n\n";
            $message .= "The funds have been returned to your account balance. If you have any questions about this decision, please contact our support team.\n\n";
            $message .= "Login to check your balance here: https://viralboast.com/login\n\n";
            $message .= "Thank you for using viralboast!\n\n";
            $message .= "This is an automated message. Please do not reply to this email.";

            Mail::raw($message, function ($mail) use ($user, $subject) {
                $mail->to($user->email, $user->name)
                     ->subject($subject)
                     ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info('Withdrawal rejected notification email sent', [
                'user_email' => $user->email,
                'amount' => $amount,
                'reason' => $reason
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send withdrawal rejected notification email', [
                'user_email' => $user->email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}

