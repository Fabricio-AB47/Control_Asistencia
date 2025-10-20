import path from 'path'
import fs from 'fs'
import { glob } from 'glob'
import { src, dest, watch, series } from 'gulp'
import * as dartSass from 'sass'
import gulpSass from 'gulp-sass'
import sharp from 'sharp'
import rename from 'gulp-rename'

const sass = gulpSass(dartSass)

const paths = {
    scss: 'src/scss/**/*.scss'
}

// Compilar SCSS
export function css(done) {
    src(paths.scss, { sourcemaps: true, allowEmpty: true })  // Permitir vacío
        .pipe(sass({ outputStyle: 'compressed' }).on('error', sass.logError))
        .pipe(dest('./build/css', { sourcemaps: '.' }));
    done();
}

// Compilar un solo archivo directamente (opcional)
export async function compilarSassDirecto(done) {
    const result = await dartSass.compileAsync('./src/scss/main.scss', {
        style: 'compressed'
    });
    fs.mkdirSync('./build/css', { recursive: true });
    fs.writeFileSync('./build/css/main-directo.css', result.css);
    done();
}

// Procesar imágenes con sharp
export async function imagenes(done) {
    const srcDir = './src/img';
    const buildDir = './build/img';
    const images = await glob('./src/img/**/*.{png,jpg,jpeg,svg}', { nodir: true });

    await Promise.all(images.map(async file => {
        const relativePath = path.relative(srcDir, path.dirname(file));
        const outputSubDir = path.join(buildDir, relativePath);
        await procesarImagenes(file, outputSubDir);
    }));

    done();
}

async function procesarImagenes(file, outputSubDir) {
    if (!fs.existsSync(outputSubDir)) {
        fs.mkdirSync(outputSubDir, { recursive: true });
    }

    const baseName = path.basename(file, path.extname(file));
    const extName = path.extname(file).toLowerCase();
    const outputFile = path.join(outputSubDir, `${baseName}${extName}`);

    if (extName === '.svg') {
        fs.copyFileSync(file, outputFile);
    } else {
        const options = { quality: 80 };
        await sharp(file).jpeg(options).toFile(outputFile);
        await sharp(file).webp(options).toFile(path.join(outputSubDir, `${baseName}.webp`));
        await sharp(file).avif().toFile(path.join(outputSubDir, `${baseName}.avif`));
    }
}

// Watcher
export function dev() {
    watch(paths.scss, css);
    watch('src/img/**/*.{png,jpg,jpeg,svg}', imagenes);
}

// Tarea por defecto
export default series(css, imagenes, dev);
