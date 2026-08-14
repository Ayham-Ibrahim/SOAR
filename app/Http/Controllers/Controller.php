<?php

namespace App\Http\Controllers;

abstract class Controller
{
     /**
     * Standardized success response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success($data, $message = 'نجاح العملية', $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Standardized error response.
     *
     * @param string $message
     * @param int $code
     * @param mixed $errors
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error($message = 'حدث خطأ ما', $code = 500, $errors = null)
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Standardized paginated response.
     *
     * @param \Illuminate\Pagination\LengthAwarePaginator $paginatedData
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function paginate($paginatedData, $message = 'تم جلب البيانات بنجاح')
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $paginatedData->items(),
            'meta' => [
                'current_page' => $paginatedData->currentPage(),
                'per_page' => $paginatedData->perPage(),
                'total' => $paginatedData->total(),
                'last_page' => $paginatedData->lastPage()
            ]
        ], 200);
    }
     protected function paginateWithData($paginatedData,$data, $message = 'تم جلب البيانات بنجاح')
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $paginatedData->items(),
            'additional_data' => $data,
            'meta' => [
                'current_page' => $paginatedData->currentPage(),
                'per_page' => $paginatedData->perPage(),
                'total' => $paginatedData->total(),
                'last_page' => $paginatedData->lastPage()
            ]
        ], 200);
    }

    /**
     * The unified curriculum hierarchy filter shared by every listing page
     * (subjects, courses, units, lessons, videos, files, exams — see
     * App\Models\Concerns\FiltersByCurriculum): only the keys the client
     * actually sent make it into the array, so services can hand it
     * straight to ->curriculumFilter() and rely on when() to skip the rest.
     *
     * @return array<string, int>
     */
    protected function curriculumFilters(\Illuminate\Http\Request $request): array
    {
        return $request->only([
            'category_id',
            'sub_category_id',
            'subject_id',
            'teacher_id',
            'course_id',
            'unit_id',
            'lesson_id',
        ]);
    }
}
