var gulp        = require('gulp');
var browserSync = require('browser-sync').create();
var sass        = require('gulp-sass');
var nodemon = require('gulp-nodemon');
var stripdebug = require('gulp-strip-debug');
var uglify = require('gulp-uglify');
var gzip = require('gulp-gzip');
var concat = require('gulp-concat');

gulp.task('prodTasks', ['sassProd','jsProd','jsLibProd','jsLibFoundationProd']);

gulp.task('devTasks', ['sass','js','jsLib', 'jsLibFoundation'], function() {

    browserSync.init({
        proxy: "jl.mixmo.fr:3002",
        // serveStatic:["js", "css"],
        ws: true,
        logLevel:'debug',
        open: false
    });

    gulp.watch("scss/*.scss", ['sass']);
    gulp.watch("js-src/*.js", ['js']);
    gulp.watch("js-src/lib/*.js", ['jsLib']);
    gulp.watch("views/*.twig").on('change', browserSync.reload);
});


/**
 * PROD TASKS
 */
gulp.task('sassProd', function() {
    return gulp.src("scss/*.scss")
        .pipe(sass({includePaths: ['node_modules/foundation-sites/scss','/data/node_modules/motion-ui/src']}))
//        .pipe(gzip({ append: false }))
        .pipe(gulp.dest("css"));
});

gulp.task('jsLibProd', function() {
    return gulp.src(["js-src/lib/*.js", "!js-src/lib/foundation*.js"])
        .pipe(gulp.dest("js"));
});
gulp.task('jsLibFoundationProd', function() {
    return gulp.src("js-src/lib/foundation*.js")
        .pipe(concat('foundation.js'))
        .pipe(gulp.dest("js"));
});
gulp.task('jsProd', function() {
    return gulp.src("js-src/*.js")
        .pipe(stripdebug())
        .pipe(uglify())
//        .pipe(gzip({ append: false }))
        .pipe(gulp.dest("js"));
});
/**
 * END PROD TASKS
 */

/**
 * DEV TASKS
 */

gulp.task('sass', function() {
    return gulp.src("scss/*.scss")
        .pipe(sass({includePaths: ['/data/node_modules/foundation-sites/scss','/data/node_modules/motion-ui/src']}))
        .pipe(gulp.dest("css"))
        .pipe(browserSync.stream());
});
gulp.task('jsLib', function() {
    return gulp.src(["js-src/lib/*.js", "!js-src/lib/foundation*.js"])
        .pipe(gulp.dest("js"))
        .pipe(browserSync.stream());
});
gulp.task('jsLibFoundation', function() {
    return gulp.src("js-src/lib/foundation*.js")
        .pipe(concat('foundation.js'))
        .pipe(gulp.dest("js"))
        .pipe(browserSync.stream());
});
gulp.task('js', function() {
    return gulp.src("js-src/*.js")
        .pipe(gulp.dest("js"))
        .pipe(browserSync.stream());
});

/**
 * END DEV TASKS
 */

gulp.task('nodemon', function (cb) {
    var callbackCalled = false;
    return nodemon({script: './server.js'}).on('start', function () {
        if (!callbackCalled) {
            callbackCalled = true;
            cb();
        }
    });
});

gulp.task('prod', ['nodemon', 'prodTasks']);
gulp.task('dev', ['nodemon', 'devTasks']);
gulp.task('default', ['jsprod']);
