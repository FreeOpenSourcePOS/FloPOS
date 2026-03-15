<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Main\OtpVerification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PasswordResetController extends Controller
{
    /**
     * Send password reset OTP to email.
     */
    public function sendResetOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->is_active) {
            return response()->json(['error' => 'User not found or inactive'], 404);
        }

        // Create OTP
        $otp = OtpVerification::createForEmail($request->email, 'password_reset', 10);

        // Send email (for now, just return OTP in response for testing)
        // In production, send actual email
        // Mail::to($user->email)->send(new PasswordResetMail($otp->otp));

        return response()->json([
            'message' => 'Password reset OTP sent to your email',
            'email' => $request->email,
            // Remove this in production:
            'otp' => $otp->otp, // Only for testing
            'expires_in_minutes' => 10,
        ]);
    }

    /**
     * Verify OTP and reset password.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify OTP
        $isValid = OtpVerification::verify($request->email, $request->otp, 'password_reset');

        if (!$isValid) {
            return response()->json([
                'error' => 'Invalid or expired OTP'
            ], 400);
        }

        // Update password
        $user = User::where('email', $request->email)->first();
        $user->update([
            'password' => $request->password, // Auto-hashed
        ]);

        return response()->json([
            'message' => 'Password reset successfully. You can now login with your new password.',
        ]);
    }

    /**
     * Change password for authenticated user.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth('api')->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'error' => 'Current password is incorrect'
            ], 400);
        }

        // Update password
        $user->update([
            'password' => $request->password, // Auto-hashed
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }
}
