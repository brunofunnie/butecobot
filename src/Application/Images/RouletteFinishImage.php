<?php

namespace Chorume\Application\Images;

use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

class RouletteFinishImage
{
    private string $fontsPath;
    private string $imagesPath;
    private string $tempPath;

    public function __construct()
    {
        $this->fontsPath = __DIR__ . '/../../Assets/Fonts/';
        $this->imagesPath = __DIR__ . '/../../Assets/Images/';
        $this->tempPath = __DIR__ . '/../../../temp/';
    }

    public function render(array $data): string
    {
        $manager = ImageManager::gd();
        $img = $manager->read($this->imagesPath . 'Roulette/roulette_finish.png');

        $imgNumber = $manager->read($this->imagesPath . "Roulette/Numbers/{$data['winner_number']}.png");
        $imgNumber->resize(250, 250);
        $img->place($imgNumber, 'top-center', 0, 0, 85);

        // Who spinned?
        $img->text($data['who_spinned'], 195, 275, function ($font) {
            $font->file($this->fontsPath . 'roboto.ttf');
            $font->size(24);
            $font->color('#FFFF00');
            $font->align('center');
            $font->valign('top');
        });

        $initialNameY = 370;
        $initialAmountY = 365;

        foreach ($data['winners'] as $winner) {
            $img->text($winner['name'], 55, $initialNameY, function ($font) {
                $font->file($this->fontsPath . 'roboto.ttf');
                $font->size(16);
                $font->color('#FFFFFF');
                $font->align('left');
                $font->valign('top');
            });

            $img->text($winner['amount'], 320, $initialAmountY, function ($font) {
                $font->file($this->fontsPath . 'roboto.ttf');
                $font->size(16);
                $font->color('#FFFFFF');
                $font->align('center');
                $font->valign('top');
            });

            $initialNameY +=  38;
            $initialAmountY +=  38;
        }

        $initialCircleX = 45;
        $initialCircleY = 560;

        $initialTextNumberX = 62;
        $initialTextNumberY = 573;

        $newCircleRed = ImageManager::gd();
        $circleRed = $newCircleRed->read($this->imagesPath . 'Roulette/red.png');
        $circleRed->resize(36, 36);

        $newCircleBlack = ImageManager::gd();
        $circleBlack = $newCircleBlack->read($this->imagesPath . 'Roulette/black.png');
        $circleBlack->resize(36, 36);

        $newCircleGreen = ImageManager::gd();
        $circleGreen = $newCircleGreen->read($this->imagesPath . 'Roulette/green.png');
        $circleGreen->resize(36, 36);

        foreach ($data['roulette_history'] as $index => $number) {
            if ($number % 2 !== 0) { // Red | Odds
                $circle = $circleRed;
            } else if ($number % 2 === 0 && $number !== 0) { // Black | Evens
                $circle = $circleBlack;
            } else { // Green
                $circle = $circleGreen;
            };

            $img->place($circle, 'top-left', $initialCircleX, $initialCircleY);

            $img->text($number, $initialTextNumberX, $initialTextNumberY, function (FontFactory $font) {
                $font->file($this->fontsPath . 'roboto-bold.ttf');
                $font->size(16);
                $font->color('#CFCFCF');
                $font->align('center');
                $font->valign('top');
            });

            $initialCircleX += 43;
            $initialTextNumberX += 43;

            if (($index + 1) % 8 === 0) {
                $initialCircleX = 45;
                $initialCircleY = 603;
                $initialTextNumberX = 62;
                $initialTextNumberY = 615;
            }
        }

        // Files
        $filenameId = $data['roulette_id'];
        $filepath = $this->tempPath . sprintf('images/%s.jpg', $filenameId);
        $oldFileNameId = $filenameId - 1;
        $oldFilepath = $this->tempPath . sprintf('images/%s.jpg', $oldFileNameId);

        if (file_exists($oldFilepath)) {
            unlink($oldFilepath);
        }

        $img->toJpeg([
            'quality' => 100
        ])->save($filepath);

        return $filepath;
    }
}
