# PowerPoint preview renderer

The Office preview ships the MIT-licensed npm package [pptx-browser](https://github.com/bowespublishing/pptx-browser) 4.1.5. The local patch uses installed fonts, initializes paragraph defaults before rendering bullets, applies the resolved text color, and bounds decompression to 96 MiB per presentation.

From a temporary build directory, set REPO to the absolute FLEXIcontent checkout and run these commands in a POSIX shell.

```sh
npm pack pptx-browser@4.1.5 --ignore-scripts
tar -xzf pptx-browser-4.1.5.tgz
cd package
git apply --unidiff-zero --ignore-space-change "$REPO/_tools/pptx-browser/viewer.patch"
npm exec --yes --package=esbuild@0.27.0 -- esbuild src/index.js --bundle --minify --format=iife --global-name=FCPresentation --outfile="$REPO/site/librairies/office-preview/pptx-browser/slides.min.js"
cp LICENSE "$REPO/site/librairies/office-preview/pptx-browser/LICENSE"
```
