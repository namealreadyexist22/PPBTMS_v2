<?php

namespace App\Core\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class UserService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected User $user)
    {}

    /**
     * Find a user model instance by ID or fail.
     */
    public function findById($id): User
    {
        // Calling find() directly on the model instance returns a pure User object
        return $this->user->findOrFail($id);
    }

    /**
     * Handle view formatting layout responses context bindings.
     */
    public function main_user($request)
    {
        $user = $request->has('id') ? $this->findById($request->id) : null;

        return view('BackEnd.auth.extras.user_entry', [
            'user'      => $user,
            'modalName' => 'USER_ENTRY_MODAL'
        ]);
    }

    /**
     * Process persistence engine pipeline updates and new structural record creations.
     */
    public function user_store($request): JsonResponse
    {
        try {
            // Include image format rules inside your custom form validation rules payload mapping layer
            $validated = $request->validated();

            // Fetch target instance or instantiate blank layout framework context
            $userId = $request->input('id');
            $userInstance = $userId ? $this->findById($userId) : new User();

            // Handle Password encryption configurations
            if (!empty($validated['password'])) {
                $validated['password'] = bcrypt($validated['password']);
            } else {
                unset($validated['password']);
            }

            // Capitalize structural text items uniformly
            if (isset($validated['minitial'])) {
                $validated['minitial'] = strtoupper($validated['minitial']);
            }

            // FILE UPLOAD HANDLING PIPELINE FOR AVATAR IMAGES
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');

                $validated['avatar_data'] = base64_encode(file_get_contents($file->getRealPath()));
                $validated['avatar_mime'] = $file->getMimeType();
            }

            // Persist the changes seamlessly
            if ($userId) {
                $userInstance->update($validated);
                $message = 'System User record updates have been applied successfully.';
            } else {
                $userInstance = $this->user->create($validated);
                $message = 'System User record has been processed and committed successfully.';
            }

            // Persist the changes seamlessly
            if ($userId) {
                $userInstance->update($validated);
                $message = 'System User record updates have been applied successfully.';
            } else {
                // Default fallback avatar configuration assignments
                if (!isset($validated['img_slug'])) {
                    $validated['img_slug'] = 'avatar-default.png';
                }
                $userInstance = $this->user->create($validated);
                $message = 'System User record has been processed and committed successfully.';
            }

            // Sync the RBAC role (single-select: empty selection clears any existing role)
            $userInstance->syncRoles($validated['role'] ?? []);

            activity()
                ->causedBy($request->user())
                ->performedOn($userInstance)
                ->log("changed password for user \"{$userInstance->fullname}\"");

            return response()->json([
                'status'  => 'success',
                'message' => $message
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Persistence processing exception thrown: ' . $e->getMessage()
            ], 500);
        }
    }

    public function user_cpass($request)
    {
        $user = $this->findById($request->id);
        return view('BackEnd.auth.extras.user_cpass', compact('user'));
    }

    // 2. Form execution destination point
    public function user_upass($request)
    {
        try {
            $userInstance = $this->findById($request->id);

            $userInstance->update([
                'password' => bcrypt($request->password)
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Account password credentials have been refreshed successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to adjust credentials data profile context: ' . $e->getMessage()
            ], 500);
        }
    }

    public function user_ustat($request): JsonResponse
    {
        // Validate that the request parameters match structural constraints securely
        $request->validate([
            'id'           => ['required', 'integer', 'exists:users,id'],
            'is_activated' => ['required', 'in:0,1']
        ]);

        try {
            $userInstance = $this->findById($request->id);

            // Prevent users from deactivating their own active profile session context
            if ($request->user()->id == $userInstance->id && $request->is_activated == 0) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Security policy breach: You cannot deactivate your own active administrative session context.'
                ], 403);
            }

            $userInstance->update([
                'is_activated' => $request->is_activated
            ]);

            $statusText = $request->is_activated == 1 ? 'activated' : 'deactivated';

            activity()
                ->causedBy($request->user())
                ->performedOn($userInstance)
                ->log("{$statusText} user \"{$userInstance->fullname}\"");

            return response()->json([
                'status'  => 'success',
                'message' => "The profile record has been successfully {$statusText}."
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Lifecycle state transition tracking exception: ' . $e->getMessage()
            ], 500);
        }
    }

   public function user_destroy($request): JsonResponse
    {
        // Validate that the request parameters match structural constraints securely
        $request->validate([
            'id' => ['required', 'integer', 'exists:users,id']
        ]);

        try {
            // Prevent users from deleting their own active profile session context
            if ($request->user()->id == $request->id) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Security policy breach: You cannot delete your own active administrative session context.'
                ], 403);
            }

            $userInstance = $this->findById($request->id);
            $fullname = $userInstance->fullname;

            activity()
                ->causedBy($request->user())
                ->performedOn($userInstance)
                ->log("deleted user \"{$fullname}\"");

            // Execute the deletion directly on the returned Model instance
            $this->findById($request->id)->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'The user profile record has been successfully deleted.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Deletion operation exception: ' . $e->getMessage()
            ], 500);
        }
    }
}
