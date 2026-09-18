import { defineConfig } from 'vite'
import tailwindcss from "@tailwindcss/vite";
import react from '@vitejs/plugin-react';
import path from 'path';



// https://vite.dev/config/
export default defineConfig({
    plugins: [
        tailwindcss(),
        react({
            babel: {
                plugins: [['babel-plugin-react-compiler']],
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './app'),
        }
    },
    
    server: {
        proxy: {
            '/api': {
                target: 'http://localhost:3000',
                changeOrigin: true,
                secure: false,
            }
        },
        allowedHosts:["0e9c5b3ef2a8ba.lhr.life"]
    }

})

