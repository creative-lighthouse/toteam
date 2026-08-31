<template>
  <div class="section section--TestPage">
    <div class="section_content">
      <h2 class="hl2">Border-Image Test</h2>
      <p>Experimentierseite für <code>Bordertest-1.svg</code> als CSS <code>border-image</code>.</p>

      <div class="test-border-box test-border-box--main" :style="borderImageStyle">
        <p>Ein einfacher Container mit dem SVG als Rahmen.</p>
      </div>

      <h3 class="hl3">border-image-repeat im Vergleich</h3>
      <div class="test-border-variants">
        <div class="test-border-variant">
          <div class="test-border-box test-border-box--compare test-border-box--stretch" :style="borderImageStyle">stretch</div>
        </div>
        <div class="test-border-variant">
          <div class="test-border-box test-border-box--compare test-border-box--round" :style="borderImageStyle">round</div>
        </div>
        <div class="test-border-variant">
          <div class="test-border-box test-border-box--compare test-border-box--repeat" :style="borderImageStyle">repeat</div>
        </div>
      </div>

      <h3 class="hl3">Einfärbbar</h3>
      <p>
        <code>border-image</code> selbst kennt kein <code>currentColor</code> — das SVG wird als Rohtext
        importiert, die Farbe per String-Ersetzung reingeschrieben und daraus eine Data-URI gebaut.
      </p>
      <label class="test-color-picker">
        Rahmenfarbe
        <input type="color" v-model="borderColor" />
      </label>
      <div class="test-border-box test-border-box--main" :style="coloredBorderImageStyle">
        <p>Dieser Rahmen nimmt die oben gewählte Farbe an.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePageHeaderStore } from '@stores/pageHeader'
import BorderTestSvg from '../../../images/borderimages/Bordertest-3.svg'
import BorderTestSvgRaw from '../../../images/borderimages/Bordertest-3.svg?raw'

usePageHeaderStore().setHeader('Test', 'Experimentierseite')

// The image lives in the "exposed" app/client/images dir, referenced as a plain
// `/_resources/...` URL. That works fine against the production build (same
// origin as the page), but breaks under `yarn dev`: main.scss's <link> tag then
// points at the Vite dev server (port 5173), and CSS `url()` resolves relative
// to the *stylesheet's* origin, not the page's — so the absolute path 404s on
// the dev server instead of hitting SilverStripe. Importing it as a JS module
// (like the icon imports elsewhere in this app) lets Vite resolve the URL
// correctly in both dev and prod, so we set it inline instead of in the SCSS.
const borderImageStyle = { borderImageSource: `url("${BorderTestSvg}")` }

// Colorable variant: the SVG's shapes either have no explicit fill (so they
// inherit from the root <svg>, which we add a fill attribute to) or an inline
// `stroke:#000` (which we replace directly) — covers every shape in this file.
const borderColor = ref('#4E9DAE')

function colorizeSvg(rawSvg, color) {
  return rawSvg
    .replace('<svg ', `<svg fill="${color}" `)
    .replaceAll('#000', color)
}

const coloredBorderImageStyle = computed(() => {
  const colored = colorizeSvg(BorderTestSvgRaw, borderColor.value)
  return { borderImageSource: `url("data:image/svg+xml,${encodeURIComponent(colored)}")` }
})
</script>
