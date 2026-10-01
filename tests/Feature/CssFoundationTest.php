<?php

use Symfony\Component\Finder\Finder;

/**
 * CSS Foundation Tests
 *
 * Verifies app.css is clean after Flux-specific CSS removal and
 * Mary UI source directive has been added.
 */
describe('app.css Flux-specific CSS removal', function () {
    beforeEach(function () {
        $this->css = file_get_contents(resource_path('css/app.css'));
    });

    it('does not contain [data-flux-field] selector', function () {
        expect($this->css)->not->toContain('[data-flux-field]');
    });

    it('does not contain [data-flux-label] selector', function () {
        expect($this->css)->not->toContain('[data-flux-label]');
    });

    it('does not contain input:focus[data-flux-control] selector', function () {
        expect($this->css)->not->toContain('input:focus[data-flux-control]');
    });

    it('does not contain flux-pro @source directive', function () {
        expect($this->css)->not->toContain("@source '../../vendor/livewire/flux-pro/stubs");
    });

    it('does not contain flux @source directive', function () {
        expect($this->css)->not->toContain("@source '../../vendor/livewire/flux/stubs");
    });

    it('does not contain zinc palette in @theme', function () {
        expect($this->css)->not->toContain('--color-zinc-50');
    });

    it('does not contain --color-accent-foreground in @theme block', function () {
        expect($this->css)->not->toContain('--color-accent-foreground');
    });

    it('does not contain @layer theme block with .dark accent overrides', function () {
        expect($this->css)->not->toContain('@layer theme');
    });

    it('does not contain flux border-color reset in @layer base', function () {
        // @layer base is legitimate in Tailwind v4; reject only flux-specific resets
        if (preg_match('/@layer base\s*\{(.*?)\}/s', $this->css, $m)) {
            expect($m[1])->not->toContain('border-color');
        }
    });
});

describe('app.css required content preservation', function () {
    beforeEach(function () {
        $this->css = file_get_contents(resource_path('css/app.css'));
    });

    it('contains Mary UI @source directive', function () {
        expect($this->css)->toContain('@source "../../vendor/robsontenorio/mary/src/View/Components/**/*.php"');
    });

    it('does not contain flux.css import', function () {
        expect($this->css)->not->toContain("@import '../../vendor/livewire/flux/dist/flux.css'");
    });

    it('contains --font-sans in @theme block', function () {
        expect($this->css)->toContain('--font-sans');
    });

    it('contains @plugin "daisyui"', function () {
        expect($this->css)->toContain('@plugin "daisyui"');
    });
});

describe('Flux-free blade audit', function () {
    it('all blade files contain no flux references', function () {
        $finder = new Finder;
        $files = $finder->files()->name('*.blade.php')->in(resource_path('views'));

        foreach ($files as $file) {
            $content = $file->getContents();
            $path = $file->getRelativePathname();

            expect($content)
                ->not->toContain('flux:', "Found flux: in {$path}")
                ->not->toContain('@fluxScripts', "Found @fluxScripts in {$path}")
                ->not->toContain('@fluxAppearance', "Found @fluxAppearance in {$path}")
                ->not->toContain('$flux.', "Found \$flux. in {$path}");
        }
    });
});

describe('Alpine cloak rule', function () {
    beforeEach(function () {
        $this->css = file_get_contents(resource_path('css/app.css'));
    });

    it('hides x-cloak elements until Alpine boots', function () {
        // x-cloak is used by the audio player, command palette and Mary modals.
        // Without this rule those panels flash on screen before hydration.
        expect($this->css)->toContain('[x-cloak]');

        expect($this->css)->toMatch('/\[x-cloak\]\s*\{[^}]*display:\s*none/');
    });
});

/**
 * Page background continuity
 *
 * daisyUI paints a background on :root, so the body's own background no longer
 * propagates to the canvas — it only covers the body's box. If the body is given
 * a definite height it stops growing with its content, and everything below the
 * first viewport shows daisyUI's base-100 instead of the canvas colour, reading
 * as a lighter band that appears when you scroll.
 */
describe('page background continuity', function () {
    beforeEach(function () {
        $this->css = file_get_contents(resource_path('css/app.css'));
    });

    it('lets the body grow with its content', function () {
        expect($this->css)->toMatch('/body\s*\{[^}]*min-height:\s*100%/');
    });

    it('never pins the body to a viewport height', function () {
        // A definite height is what produced the seam: the body box stopped at
        // 100vh and the canvas colour ended with it.
        expect($this->css)->not->toMatch('/body\s*\{[^}]*[^-\w]height:\s*100%/');
    });

    it('keeps the canvas on the body, where the layout applies it', function () {
        $layout = file_get_contents(
            resource_path('views/layouts/app/sidebar.blade.php')
        );

        expect($layout)->toContain('bg-base-200');
    });
});
