import dotPreset from './dot-ui/tailwind/tailwind-preset.cjs';

/**
 * The signed-in company workspace. It styled itself from the Tailwind Play CDN with the reset switched off, because its
 * layout draws its own base from variables. This builds the same utilities into one local file instead of running a
 * third-party script in every signed-in session.
 */
export default {
    presets: [dotPreset],
    content: ['./resources/views/**/*.blade.php', './app/Livewire/**/*.php', './app/Services/IntelligenceEngineService.php'],
    corePlugins: { preflight: false },
    plugins: [],
};
