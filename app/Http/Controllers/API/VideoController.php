<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterVideoRequest;
use App\Http\Requests\UploadVideoRequest;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use App\Models\Violation;
use App\Services\MediaUploadService;
use App\Services\NotificationService;
use App\Services\VideoApprovalService;
use App\Services\VideoRetrievalService;
use Exception;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    private VideoRetrievalService $videoRetrievalService;

    private VideoApprovalService $videoApprovalService;

    private MediaUploadService $mediaUploadService;

    private NotificationService $notificationService;

    public function __construct(VideoRetrievalService $videoRetrievalService, VideoApprovalService $videoApprovalService, MediaUploadService $mediaUploadService, NotificationService $notificationService)
    {
        $this->middleware('auth:sanctum')->only(['uploadVideo']);

        $this->videoRetrievalService = $videoRetrievalService;
        $this->videoApprovalService = $videoApprovalService;
        $this->mediaUploadService = $mediaUploadService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get All Videos
     *
     * Retrieves a collection of all videos available in the system.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getAllVideos()
    {
        return VideoResource::collection($this->videoRetrievalService->getAllVideos());
    }

    /**
     * Get Video Feed
     *
     * Retrieves a filtered collection of videos based on the provided criteria.
     * This can include filtering by category, tags, or any other specified attributes.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getVideoFeed(FilterVideoRequest $request)
    {
        $validated = $request->validated();

        return VideoResource::collection($this->videoRetrievalService->getVideoFeed($validated))
            ->additional(['order' => empty($validated) ? 'default' : 'filtered']);
    }

    /**
     * Get Video
     *
     * Retrieves a single video by its ID. This method also checks for the video's privacy settings
     * and determines if the current user has permission to view the video.
     *
     * @return VideoResource
     */
    public function getVideo(string $id)
    {
        $video = Video::findOrFail($id);

        $user = auth('sanctum')->user();
        if ($video->privacy === 'private' && (! $user || ! $video->isOwnedBy($user))) {
            throw new UnauthorizedException(403, 'You do not have permission to view this video.');
        }

        //        return $this->videoRetrievalService->streamVideo($video);
        return new VideoResource($video);
    }

    /**
     * Upload Video
     *
     * Handles the uploading of a new video to the system. This method processes the uploaded video file,
     * stores it, and returns the filename of the stored video.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadVideo(UploadVideoRequest $request)
    {
        $filename = $this->mediaUploadService->upload($request->file('video'), 'videos');

        return response()->json([
            'message' => 'Video uploaded successfully',
            'filename' => $filename,
        ]);
    }

    /**
     * Approve Video
     *
     * Marks a video as approved. This is typically used in a moderation workflow where videos
     * need to be reviewed before they are made available to the public.
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws Exception
     */
    public function approveVideo(string $id)
    {
        $video = Video::findOrFail($id);
        $result = $this->videoApprovalService->approveVideo($video);

        if (! $result) {
            throw new Exception('Failed to approve video.');
        }

        $this->notificationService->createNotification([
            'user_id' => $video->listing->user_id,
            'title' => 'Video Approved',
            'message' => "Your video for the listing \"{$video->listing->name}\" has been approved.",
            'type' => 'listing',
            'action_id' => $video->listing->id,
        ]);

        return response()->json([
            'message' => 'Video approved successfully',
            'is_approved' => true,
        ]);
    }

    public function getContentModeration()
    {
        $videos = Video::with([
            'violations:id,name',
            'listing' => function ($query) {
                $query->select('id', 'name', 'user_id', 'description')
                    ->with([
                        'user:id,firstname,lastname,email',
                        'images:id,listing_id,filename,privacy' // include images
                    ]);
            },
        ])->get()->map(function ($video) {
            return [
                ...$video->toArray(),
                'violations' => $video->violations->pluck('id'),
                'host' => $video->listing?->user,
                'moderator' => $video->moderated_by,
            ];
        });

        return response()->json([
            'videos' => $videos
        ]);
    }

    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'is_approved'   => 'nullable|in:1,2',
            'violations'    => 'nullable|array',
            'violations.*'  => 'integer|exists:violations,id',
            'moderated_by'  => 'nullable',
        ]);

        $moderatedBy = $validated['moderated_by'];
        $video = Video::findOrFail($request->route('id'));

        $isTranscoded = 0;
        if ($validated['is_approved'] == 1) {
            $isTranscoded = 1;
        }
        $video->update([
            'is_approved' => $validated['is_approved'],
            'is_transcoded' => $isTranscoded,
            'moderated_by' => $moderatedBy,
            'updated_at' => now(),
        ]);

        if (isset($validated['violations'])) {
            $video->violations()->sync($validated['violations']);
        }

        $video->load('violations:id,name', 'moderator:id,firstname,lastname,email');

        return response()->json([
            'message' => 'Video status updated successfully',
            'video' => [
                ...$video->toArray(),
                'violations' => $video->violations->pluck('id'),
                'moderator' => $video->moderator,
            ]
        ]);
    }

    public function violations()
    {
        $validations = Violation::all();
        return response()->json([
            'violations' => $validations
        ]);
    }
}
