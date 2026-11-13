# Dot.Analytics: design

Direction for the public library and the signed-in workspace. Written with the awwwards skill and checked with the Impeccable detector. The product is a reading product first: a person should be able to trust what they read within three seconds of arriving.

## Concept card

```
Idea:        Every figure shows its working. Nothing is printed without its source, period, update and licence, and what we do not have is drawn as missing.
Metaphor:    An audit trail. A number cannot post to the page until it has been signed by a source.
Signature:   "Show working": any figure opens a dark working tape beneath it (definition, source, licence, period, when it was updated and when it is due, every revision, the table behind it). Native <details>, no script needed.
Emotion:     A calm reading room. Someone has checked this.
Motion verb: Post. Values post into place (a short settle, 8px, 180ms), and the working tape unrolls. Nothing else moves at rest. A still page is a decision here: a reading product should hold still and let the figures and the freshness clock do the talking.
Screenshot:  South Africa and mining: one large figure, a short chart, the working tape open in ink beneath it, a coverage strip with the gaps hatched, and a row of InfoDot mining businesses.
```

**Anchor.** The facts the concept depends on are the ones the research found: no competitor shows a source on every row, most useful series are stale or missing for most African countries, and licences differ per series. Put another product's name in the sentence and it fails: nobody else is obliged to show a licence and a gap on each number.

**Concepts rejected.** A teleprinter newswire (the product holds too little news to carry it). A world map of coverage (a map would invent precision for countries we cover only annually).

**Genericness test.** A generic model would give dark navy, a gold or acid accent, four stat cards and a gradient hero. This direction instead is light, uses tonal fields rather than hairline grids and cards, makes absence a visible part of the system, and puts the one dark object (the working tape) inside the light page.

## Palette

| Role | Light | Dark | Use |
|---|---|---|---|
| ground | `#f4f6f8` | `#0e1116` | the page |
| field | `#ffffff` | `#161b22` | a plate holding a figure |
| ink | `#10151c` | `#eef1f5` | text. The working tape is ink on light pages |
| muted | `#566070` | `#9aa6b6` | secondary text. 5.9:1 on ground |
| line | `#d7dce3` | `#2a323d` | the few rules that remain |
| accent | `#2338d8` | `#8fa2ff` | links, the chart line, focus |
| rise | `#12704a` | `#58c796` | an improvement, only ever with a word and an arrow shape |
| fall | `#b3261e` | `#ff8a80` | a deterioration, same rule |
| stale | `#8a5a00` | `#e0b04a` | out of date, hatched as well as coloured |

Colour never carries meaning alone: rise and fall carry a word and an arrow, stale carries a hatch, coverage carries a pattern.

## Type

- **IBM Plex Sans Condensed** 600 and 700 for headlines and large figures: dense, newsroom authority without a serif. Tracking -0.015em at display size, leading 1.02.
- **IBM Plex Sans** (variable 400 to 600) for text and interface. 17px body on 1.55.
- **IBM Plex Mono** 400 and 500 only for the provenance line and codes, where alignment is the point.
- Figures use tabular numerals. Numbers and units are joined with a no-break space. Sentence case everywhere. No em or en dashes.
- Fluid display: `clamp(40px, 7vw, 92px)` for the front page statement, `clamp(30px, 4.5vw, 56px)` for page titles, `clamp(22px, 3vw, 32px)` for section heads.
- Self-hosted woff2 in `public/fonts`. No third-party request on a public page.

## Grid and rhythm

- 12 columns, 1240px maximum, gutter 24px (16px on a phone), page margin `clamp(16px, 4vw, 56px)`.
- Alignment rule: figures, dates and sources align to the same left edge as the headline above them. Sources and dates sit in a left gutter on list pages.
- Section rhythm alternates density: statement, dense plate stack, open list, matrix, one full-width band, open list, footer. No two neighbours share a layout.

## Shape

One radius: 4px on plates and buttons, 2px on chips. No shadows except the working tape (a single hard offset line beneath it), no gradients, no glass.

## Motion

- One easing: `cubic-bezier(0.16, 1, 0.3, 1)`. Durations: 120ms for hover, 180ms for a figure posting, 260ms for the tape.
- Hover on primary targets: an index row moves its label 6px right and its coverage strip fills; a figure plate lifts its provenance line to full contrast. Utility links change colour only.
- The tape unrolls with a grid-template-rows transition. Under reduced motion everything is simply present.
- Charts draw nothing on load. A readout follows the pointer and the keyboard.

## Composition plan

```
HOME   statement (7 cols) + "the numbers today" plate stack (5 cols)
       newsroom: date gutter, headline, publisher, label; no cards
       by industry: a full-width index, giant names, a coverage strip on the right of each row
       by country: a coverage matrix, rows countries, columns key measures, values tinted by freshness
       what moved: a full-width ink band with three plain sentences from the numbers
       how it is labelled: the key of five labels and what is not shown yet
       InfoDot: a short row, only where a real directory search exists
LANDING (signed in, /dashboard) the real logo and the way back to InfoDot; welcome, statement and one action (7 cols)
       beside the working tape (5 cols): the newest stored figure from each reporting platform, each with the platform
       that sent it, the day it is for and when it arrived; with nothing reporting the tape is hatched and prints no number
       waiting for a decision: open alerts and recommended actions as rows, or one sentence
       your business platforms (from InfoDot, when switched on); the rest of the workspace as an index, no cards
FIGURE one large figure, the chart, the working tape, related figures, the table
INDUSTRY title, coverage strip, figures, newsroom, InfoDot businesses, what is missing
COUNTRY  title, coverage strip, figures by topic, newsroom, comparison with neighbours
```

## Components

Plate, figure, working tape, coverage strip, chart, index row, matrix, newsroom row, label key, provenance line. All in `resources/css/public.css`, all server-rendered Blade components under `resources/views/components/intel`.

## Do

- Show the period and the source next to every figure.
- Say plainly when something is not available and why.
- Prefer a sentence to a chart when a sentence answers the question.

## Don't

- Print a figure with no source, or a stale one without its date.
- Use colour as the only signal.
- Add a number that is not computed from a stored figure.
- Use gradients, glows, glass, side-stripe cards or tracked uppercase eyebrows.
