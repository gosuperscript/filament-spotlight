import * as esbuild from 'esbuild'

const watch = process.argv.includes('--watch')

const options = {
    entryPoints: {
        spotlight: 'resources/js/index.tsx',
    },
    outdir: 'dist',
    bundle: true,
    minify: true,
    format: 'iife',
    target: 'es2020',
    jsx: 'automatic',
    // The bundle is a closed IIFE — nothing React-shaped is exposed to the
    // host page — so cmdk runs on preact/compat purely as a size
    // optimization (~64 KB → ~24 KB gzipped).
    alias: {
        'react': 'preact/compat',
        'react-dom/client': 'preact/compat/client',
        'react-dom': 'preact/compat',
        'react/jsx-runtime': 'preact/jsx-runtime',
    },
    define: {
        'process.env.NODE_ENV': '"production"',
    },
    logLevel: 'info',
}

const cssOptions = {
    entryPoints: {
        spotlight: 'resources/css/spotlight.css',
    },
    outdir: 'dist',
    bundle: true,
    minify: true,
    logLevel: 'info',
}

if (watch) {
    const [js, css] = await Promise.all([esbuild.context(options), esbuild.context(cssOptions)])
    await Promise.all([js.watch(), css.watch()])
} else {
    await Promise.all([esbuild.build(options), esbuild.build(cssOptions)])
}
