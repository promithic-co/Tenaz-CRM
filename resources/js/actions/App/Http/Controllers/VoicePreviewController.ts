import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\VoicePreviewController::preview
 * @see app/Http/Controllers/VoicePreviewController.php:30
 * @route '/voz/preview-tts'
 */
export const preview = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: preview.url(options),
    method: 'post',
})

preview.definition = {
    methods: ["post"],
    url: '/voz/preview-tts',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\VoicePreviewController::preview
 * @see app/Http/Controllers/VoicePreviewController.php:30
 * @route '/voz/preview-tts'
 */
preview.url = (options?: RouteQueryOptions) => {
    return preview.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\VoicePreviewController::preview
 * @see app/Http/Controllers/VoicePreviewController.php:30
 * @route '/voz/preview-tts'
 */
preview.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: preview.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\VoicePreviewController::preview
 * @see app/Http/Controllers/VoicePreviewController.php:30
 * @route '/voz/preview-tts'
 */
    const previewForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: preview.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\VoicePreviewController::preview
 * @see app/Http/Controllers/VoicePreviewController.php:30
 * @route '/voz/preview-tts'
 */
        previewForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: preview.url(options),
            method: 'post',
        })
    
    preview.form = previewForm
const VoicePreviewController = { preview }

export default VoicePreviewController