<?php

namespace App\Services;

use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class UserAccountService
{
    public function __construct(
        private readonly EmailAvailabilityService $emailAvailability,
    ) {}

    public function updateProfile(int $userId, string $name, string $email, ?string $textProfile): array
    {
        if ($this->emailAvailability->emailTakenByOther($userId, $email)) {
            return ['errors' => trans('main.user_email_token')];
        }

        $updated = User::where('id', $userId)->update([
            'name' => $name,
            'email' => $email,
            'text_profile' => $textProfile,
        ]);

        if ($updated) {
            return ['success' => trans('main.user_done_edit_info')];
        }

        return ['errors' => trans('main.error_somethings')];
    }

    public function changePassword(User $user, string $currentPassword, string $password): array
    {
        if (! Hash::check($currentPassword, $user->password)) {
            return ['errors' => 'كلمة المرور الحالية غير صحيحة .'];
        }

        if ($currentPassword === $password) {
            return ['errors' => 'لايمكن تعديل كلمة المرور لانها مستخدمة من قبل'];
        }

        $user->password = bcrypt($password);
        $user->save();

        return ['success' => 'تم تغيير كلمة المرور بنجاح'];
    }

    public function updateProfileImage(User $user, string $dataUri): array
    {
        $imageArray1 = explode(';', $dataUri);
        $imageArray2 = explode(',', $imageArray1[1]);
        $base64 = base64_decode($imageArray2[1]);

        $imageName = 'img_'.time().$user->id.'.png';
        $path = public_path().'/images/profile/';
        $oldImage = $user->image;

        $success = file_put_contents($path.$imageName, $base64);

        if (! $success) {
            return ['error' => 'حدث خطاء , الرجاء المحاولة لاحقا'];
        }

        $user->image = $imageName;
        $user->save();

        if ($oldImage) {
            File::delete($path.$oldImage);
        }

        return ['success' => ' تم تحديث الصورة بنجاح'];
    }

    public function updateSettings(User $user, array $flags): array
    {
        $user->is_public = $flags['is_public'] ?? false;
        $user->accept_posts = $flags['accept_posts'] ?? false;
        $user->show_zwar = $flags['show_zwar'] ?? false;
        $user->active_notification = $flags['active_notification'] ?? false;
        $user->save();

        return ['success' => 'تم حفظ الاعدادات بنجاح'];
    }

    public function updateSocialLinks(User $user, array $links): array
    {
        foreach (['web', 'twitter', 'instagram', 'youtube', 'snapchat', 'telegram', 'facebook', 'linkedin'] as $field) {
            $user->{$field} = $links[$field] ?? null;
        }

        $user->save();

        return ['success' => 'تم حفظ الاعدادات بنجاح'];
    }
}
