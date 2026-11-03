<?php
declare(strict_types=1);

namespace CajunWebPro\Catering;

/** Declarative Catering layouts consumed by Core's shared theme collection. */
final class ThemeCollection
{
    /** @return list<array<string,mixed>> */
    public static function variants(): array
    {
        $definitions = [
            ['heritage-editorial', 'Heritage Editorial', 'Story-led split editorial composition.'],
            ['modern-culinary', 'Modern Culinary', 'Asymmetric modular culinary showcase.'],
            ['garden-celebration', 'Garden Celebration', 'Layered organic event presentation.'],
            ['grand-wedding', 'Grand Wedding', 'Cinematic full-bleed celebration layout.'],
            ['corporate-table', 'Corporate Table', 'Precise information-first event grid.'],
            ['rustic-gathering', 'Rustic Gathering', 'Warm collage and card composition.'],
            ['bayou-night', 'Bayou Night', 'Dark immersive hospitality experience.'],
            ['market-kitchen', 'Market Kitchen', 'Menu-first storefront composition.'],
            ['social-feast', 'Social Feast', 'Image-led masonry and event discovery.'],
            ['minimal-luxe', 'Minimal Luxe', 'Restrained typography and open-space composition.'],
        ];

        return array_map(static function (array $definition): array {
            [$id, $name, $description] = $definition;
            $pack = 'theme-packs/' . $id . '-';
            return [
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'layout' => 'views/themes/' . $id . '.php',
                'thumbnail' => 'assets/images/themes/' . $id . '.webp',
                'assets' => ['css' => ['assets/css/catering.css', 'assets/css/themes.css'], 'js' => ['assets/js/catering.js']],
                'palettes' => self::palettes($id),
                'media_slots' => [
                    'hero' => ['label' => $name . ' hero', 'default' => 'assets/images/' . $pack . 'hero.webp'],
                    'feature' => ['label' => $name . ' feature', 'default' => 'assets/images/' . $pack . 'feature.webp'],
                    'accent' => ['label' => $name . ' accent', 'default' => 'assets/images/' . $pack . 'accent.webp'],
                ],
            ];
        }, $definitions);
    }

    /** @return array<string,array{name:string,tokens:array<string,string>}> */
    private static function palettes(string $variant): array
    {
        $sets = [
            'heritage-editorial' => [['Ivory & Roux','#fffaf0','#24170f','#8b3f23','#c99545','#f4ead8'],['Magnolia','#fffdf8','#243126','#496b4c','#b7803f','#eef3e9'],['Ink & Brass','#f8f4e8','#171b23','#303c58','#a87924','#ece5d2'],['Creole Rose','#fff8f6','#351d24','#8f3d50','#b77a3d','#f4e7e4']],
            'modern-culinary' => [['Porcelain','#fbfcfd','#13202a','#176b76','#d26a3d','#edf2f4'],['Saffron Studio','#fffaf1','#282015','#9a5a0a','#be3f2f','#f5ead4'],['Juniper','#f5faf7','#14281e','#247052','#ba7c32','#e5f0e9'],['Graphite','#f4f5f6','#16191d','#454c55','#a24934','#e7e9eb']],
            'garden-celebration' => [['Sage Petal','#fbfaf5','#233126','#52735b','#b56b70','#ecf1e7'],['Lavender Field','#fcf9ff','#30263c','#70558b','#ad6e42','#eee7f5'],['Citrus Grove','#fffdf2','#27301d','#66813c','#c67527','#eef3dd'],['Blue Hydrangea','#f7fbff','#1d2c3c','#416f91','#bd6f55','#e5f0f7']],
            'grand-wedding' => [['Champagne','#fffaf2','#2a211b','#9b7135','#6d3444','#f2e9da'],['Black Tie','#f8f7f4','#151515','#393939','#9a742d','#e9e6df'],['Pearl Rose','#fff9fa','#302126','#9b5f69','#8a713d','#f4e8eb'],['Midnight Vow','#f6f7fb','#182036','#344b78','#9a7134','#e5e8f1']],
            'corporate-table' => [['Executive Blue','#f7f9fc','#182438','#285b92','#b95b34','#e7edf5'],['Slate Signal','#f8fafb','#20282e','#4a6572','#b76a2f','#e8edef'],['Forest Ledger','#f6faf7','#182b22','#32634a','#a66334','#e3eee7'],['Burgundy Brief','#fcf8f8','#331d24','#7e3347','#a97835','#f1e5e8']],
            'rustic-gathering' => [['Oat & Cedar','#fffaf1','#302216','#7a4b2c','#b76d32','#f1e5d3'],['Farmhouse Green','#faf9f1','#253025','#526448','#a66a34','#e9eadc'],['Harvest Red','#fff8f3','#371f18','#8f432f','#b18235','#f1e2d7'],['Blue Crockery','#f8fbfc','#1e2d34','#476d7d','#b26c39','#e5edf0']],
            'bayou-night' => [['Moonlit Cypress','#111915','#f5f0df','#8dbb73','#d59a3a','#1d2922'],['Velvet Ember','#1d1114','#fff3e8','#c35b47','#d7a74c','#2b1b1e'],['Deep Indigo','#101522','#f4f1e8','#617cb0','#d39c42','#1d2537'],['Night Orchid','#19121d','#f7eff7','#9f6aa8','#d0924c','#281d2d']],
            'market-kitchen' => [['Tomato Paper','#fffaf3','#2f2119','#b8432f','#3f7150','#f3e5d8'],['Market Green','#f9fbf4','#202d20','#47744c','#c26a2f','#e8efe0'],['Butcher Blue','#f7fafc','#1c2934','#3c6a89','#bd5835','#e6eef3'],['Mustard Ink','#fffbef','#292318','#a66b13','#7a3f2e','#f3ead0']],
            'social-feast' => [['Confetti','#fff9f7','#32202b','#b94770','#287d75','#f2e6ed'],['Poolside','#f5fcfd','#173038','#287d8a','#d26146','#e0f1f3'],['Sunset','#fff8f1','#35201a','#c24f33','#87538f','#f5e2d7'],['Festival','#faf8ff','#281f39','#6750a4','#bc5a3b','#ebe6f5']],
            'minimal-luxe' => [['Warm White','#fdfcf9','#1f1d1a','#6f5a3a','#8c4235','#efebe3'],['Gallery Gray','#fafafa','#202020','#555555','#8a6931','#ececec'],['Soft Olive','#fbfbf5','#272a20','#63694b','#8f553d','#eceee2'],['Quiet Navy','#f8f9fb','#1c2432','#44526d','#9a7139','#e8ebf0']],
        ];
        $palettes = [];
        foreach ($sets[$variant] as $index => $set) {
            [$name, $background, $text, $primary, $accent, $surface] = $set;
            $palettes['palette-' . ($index + 1)] = ['name' => $name, 'tokens' => compact('background','text','primary','accent','surface')];
        }
        return $palettes;
    }
}
