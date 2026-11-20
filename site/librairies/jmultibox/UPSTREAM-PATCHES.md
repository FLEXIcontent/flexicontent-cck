# Local Multibox stylesheet patch

The gallery and optional Vegas backdrop use the Joomla/Bootstrap modal stacking
levels (1055 and 1050). The original levels (2 and 1) placed them below the
Cassiopeia header (10). At a 768px viewport the header covered the close button
on the persistent gallery fixture, preventing a normal pointer click.

`tests/native-public-widgets.cjs` exercises pointer hit testing on the close
control and backdrop with a positioned header and the shipped stylesheet order.
Keep these stacking levels when refreshing the vendor package.

The readable and minified `resize` implementations also fit image content to the
viewport before positioning the viewer. They preserve aspect ratio, avoid
enlarging smaller images, and reserve space for borders, the outside close
button and controls. `tests/multibox-layout.cjs` checks both shipped scripts with
wide, tall and small images at 390, 768 and 1280 pixels. The gallery interaction
API and non-image content dimensions are unchanged.

Escape now closes an open viewer. The key listener is registered only while the
viewer is open and removed on close/reset. Both shipped scripts are covered by
the open, Escape, reopen sequence in `tests/multibox-layout.cjs`. Rebuild the
minified script from `js/jmultibox.js` with Terser 5.44.1 when changing it.
