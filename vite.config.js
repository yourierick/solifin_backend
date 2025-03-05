import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react({
            jsxRuntime: 'automatic',
            jsxImportSource: 'react',
            babel: {
                presets: ['@babel/preset-react'],
                plugins: ['@babel/plugin-transform-react-jsx']
            }
        }),
    ],
    server: {
        hmr: {
            overlay: true
        }
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            'react': path.resolve(__dirname, './node_modules/react'),
            'react-dom': path.resolve(__dirname, './node_modules/react-dom'),
        },
        extensions: ['.js', '.jsx']
    },
    optimizeDeps: {
        include: ['react', 'react-dom'],
        force: true
    },
    build: {
        commonjsOptions: {
            transformMixedEsModules: true
        }
    }
});
