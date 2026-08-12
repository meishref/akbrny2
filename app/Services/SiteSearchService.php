<?php

namespace App\Services;

use App\User;
use Illuminate\Support\Collection;

class SiteSearchService
{
    /**
     * Legacy AJAX: HTML embedded in JSON success key.
     */
    public function searchHtml(string $query): array
    {
        $users = $this->findPublicUsers($query);

        if ($users->isEmpty()) {
            return ['success' => 'لاتوجد نتائج لبحثك'];
        }

        $output = '<ul class="list-group">';
        foreach ($users as $row) {
            $img = asset($row->image ? 'images/profile/'.$row->image : 'img/avatar2.png');
            $url = route('user.getUser', $row->username);
            $output .= '<li class="list-group-item"><a href="'.$url.'"><img src="'.$img.'" height="30" width="30"/> '.$row->name.'</a></li> ';
        }
        $output .= '</ul>';

        return ['success' => $output];
    }

    /**
     * REST API: structured JSON (new contract for /api/v1/search).
     */
    public function searchJson(string $query): array
    {
        $users = $this->findPublicUsers($query);

        if ($users->isEmpty()) {
            return [
                'data' => [],
                'message' => 'لاتوجد نتائج لبحثك',
            ];
        }

        return [
            'data' => $users->map(fn (User $user) => [
                'name' => $user->name,
                'username' => $user->username,
                'profile_url' => route('user.getUser', $user->username),
                'image_url' => asset($user->image ? 'images/profile/'.$user->image : 'img/avatar2.png'),
            ])->values()->all(),
        ];
    }

    private function findPublicUsers(string $query): Collection
    {
        if ($query === '') {
            return collect();
        }

        return User::query()
            ->select(['id', 'name', 'username', 'image'])
            ->where('name', 'like', '%'.$query.'%')
            ->orWhere('username', 'like', '%'.$query.'%')
            ->where('is_public', 1)
            ->get();
    }
}
