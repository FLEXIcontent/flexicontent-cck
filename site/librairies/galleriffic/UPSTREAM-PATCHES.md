# Local compatibility patches

`js/jquery.galleriffic.js` retains the bundled jQuery gallery API. The Joomla 6
audit fixes zero/missing stored image dimensions: only positive dimensions are
applied, otherwise natural dimensions and a `100%` maximum width are used.

Play/pause links are built with DOM attributes so translated icon HTML cannot
break their title attributes. `css/basic.css` keeps the overlay controls visible
and clickable instead of clipping them inside a zero-height container.

Preserve or reconcile these changes when updating the vendor library. Live
tests cover pause, next image and popup-disabled mode at desktop/mobile widths.
