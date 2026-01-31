<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\User;
use App\Models\Community;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class CommunityControllerAPI extends Controller
{
	/**
	 * Register a new team (community) for the logged-in user
	 */
	public function registerTeam(Request $request)
	{
		$validated = $request->validate([
			'full_name' => 'required|string|max:255',
			'location' => 'nullable|string|max:255',
			'phone' => 'nullable|string|max:50',
			'members' => 'nullable|integer|min:0',
			'description' => 'nullable|string|max:1000',
		]);

		$actor = $request->user();
		Log::info('registerTeam called', ['actor' => $actor?->id]);

		if (!($actor instanceof User)) {
			Log::warning('registerTeam: unauthorized actor', ['actor' => $actor?->id]);
			return response()->json([
				'status' => 'error',
				'message' => 'This endpoint is only for authenticated users.'
			], 403);
		}

		try {
			$community = Community::create([
				'full_name' => $validated['full_name'],
				'location' => $validated['location'] ?? null,
				'phone' => $validated['phone'] ?? null,
				'members' => $validated['members'] ?? 0,
				'description' => $validated['description'] ?? null,
				'user_id' => $actor->id,
			]);

			Log::info('registerTeam: community created', ['community_id' => $community->id]);

			return response()->json([
				'status' => 'success',
				'message' => 'Team registered successfully.',
				'community' => $community,
			], 201);
		} catch (Throwable $e) {
			Log::error('registerTeam failed', ['error' => $e->getMessage()]);
			return response()->json([
				'status' => 'error',
				'message' => 'Failed to register team. See server logs for details.'
			], 500);
		}
	}

	/**
	 * Edit an existing team (community) owned by the logged-in user
	 */
	public function editTeam(Request $request, $communityId)
	{
		$validated = $request->validate([
			'full_name' => 'nullable|string|max:255',
			'location' => 'nullable|string|max:255',
			'phone' => 'nullable|string|max:50',
			'members' => 'nullable|integer|min:0',
			'description' => 'nullable|string|max:1000',
		]);

		$actor = $request->user();
		Log::info('editTeam called', ['actor' => $actor?->id, 'community_id' => $communityId]);

		if (!($actor instanceof User)) {
			Log::warning('editTeam: unauthorized actor', ['actor' => $actor?->id]);
			return response()->json([
				'status' => 'error',
				'message' => 'This endpoint is only for authenticated users.'
			], 403);
		}

		try {
			$community = Community::find($communityId);

			if (!$community) {
				Log::warning('editTeam: community not found', ['community_id' => $communityId]);
				return response()->json([
					'status' => 'error',
					'message' => 'Team not found.'
				], 404);
			}

			if ($community->user_id !== $actor->id) {
				Log::warning('editTeam: unauthorized access', ['actor' => $actor->id, 'community_user' => $community->user_id]);
				return response()->json([
					'status' => 'error',
					'message' => 'You are not authorized to edit this team.'
				], 403);
			}

			/** @var Community $community */
			$community->update($validated);
			Log::info('editTeam: community updated', ['community_id' => $community->id]);

			return response()->json([
				'status' => 'success',
				'message' => 'Team updated successfully.',
				'community' => $community,
			], 200);
		} catch (Throwable $e) {
			Log::error('editTeam failed', ['error' => $e->getMessage()]);
			return response()->json([
				'status' => 'error',
				'message' => 'Failed to edit team. See server logs for details.'
			], 500);
		}
	}

	/**
	 * Show all teams (communities) owned by the logged-in user
	 */
	public function showTeams(Request $request)
	{
		$actor = $request->user();
		Log::info('showTeams called', ['actor' => $actor?->id]);

		if (!($actor instanceof User)) {
			Log::warning('showTeams: unauthorized actor', ['actor' => $actor?->id]);
			return response()->json([
				'status' => 'error',
				'message' => 'This endpoint is only for authenticated users.'
			], 403);
		}

		try {
			$communities = Community::where('user_id', $actor->id)->get();
			return response()->json([
				'status' => 'success',
				'communities' => $communities,
			], 200);
		} catch (Throwable $e) {
			Log::error('showTeams failed', ['error' => $e->getMessage()]);
			return response()->json([
				'status' => 'error',
				'message' => 'Failed to fetch teams. See server logs for details.'
			], 500);
		}
	}

	/**
	 * Delete a team (community) owned by the logged-in user
	 */
	public function deleteTeam(Request $request, $communityId)
	{
		$actor = $request->user();
		Log::info('deleteTeam called', ['actor' => $actor?->id, 'community_id' => $communityId]);

		if (!($actor instanceof User)) {
			Log::warning('deleteTeam: unauthorized actor', ['actor' => $actor?->id]);
			return response()->json([
				'status' => 'error',
				'message' => 'This endpoint is only for authenticated users.'
			], 403);
		}

		try {
			$community = Community::find($communityId);

			if (!$community) {
				Log::warning('deleteTeam: community not found', ['community_id' => $communityId]);
				return response()->json([
					'status' => 'error',
					'message' => 'Team not found.'
				], 404);
			}

			if ($community->user_id !== $actor->id) {
				Log::warning('deleteTeam: unauthorized access', ['actor' => $actor->id, 'community_user' => $community->user_id]);
				return response()->json([
					'status' => 'error',
					'message' => 'You are not authorized to delete this team.'
				], 403);
			}

			/** @var Community $community */
			$community->delete();
			Log::info('deleteTeam: community deleted', ['community_id' => $communityId]);

			return response()->json([
				'status' => 'success',
				'message' => 'Team deleted successfully.'
			], 200);
		} catch (Throwable $e) {
			Log::error('deleteTeam failed', ['error' => $e->getMessage()]);
			return response()->json([
				'status' => 'error',
				'message' => 'Failed to delete team. See server logs for details.'
			], 500);
		}
	}
}
