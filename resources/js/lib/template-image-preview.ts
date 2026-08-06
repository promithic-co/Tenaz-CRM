export async function createTemplateImagePreview(file: File): Promise<File> {
    const sourceUrl = URL.createObjectURL(file);

    try {
        const image = await loadImage(sourceUrl);
        const scale = Math.min(
            1,
            640 / image.naturalWidth,
            640 / image.naturalHeight,
        );
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
        canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));

        const context = canvas.getContext('2d');
        if (!context) {
            throw new Error('Canvas is unavailable.');
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        const blob = await canvasToCompactBlob(canvas);

        return new File([blob], 'preview.jpg', { type: 'image/jpeg' });
    } finally {
        URL.revokeObjectURL(sourceUrl);
    }
}

function loadImage(source: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('Could not decode image.'));
        image.src = source;
    });
}

async function canvasToCompactBlob(canvas: HTMLCanvasElement): Promise<Blob> {
    for (const quality of [0.82, 0.68, 0.54]) {
        const blob = await canvasToBlob(canvas, quality);

        if (blob.size <= 300 * 1024) {
            return blob;
        }
    }

    throw new Error('Could not generate a compact preview.');
}

function canvasToBlob(
    canvas: HTMLCanvasElement,
    quality: number,
): Promise<Blob> {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(new Error('Could not generate a compact preview.'));
                    return;
                }

                resolve(blob);
            },
            'image/jpeg',
            quality,
        );
    });
}
