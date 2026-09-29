<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Génère un QR code en SVG (aucune extension PHP requise, imprimable sans perte). */
class QrImage
{
    public static function svg(string $contenu, int $taille = 320): string
    {
        $renderer = new ImageRenderer(new RendererStyle($taille, 2), new SvgImageBackEnd());

        return (new Writer($renderer))->writeString($contenu);
    }
}
