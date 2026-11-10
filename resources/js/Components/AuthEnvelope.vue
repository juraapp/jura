<script setup>
/**
 * Decorative animated envelope for the password-recovery screens. The flap
 * flips open in 3D, a letter carrying a key rises out, slides back in, the
 * flap flips closed and a seal pops on, on a loop.
 *
 * Layers are stacked SVGs sharing one 320x300 coordinate space; the flap is
 * HTML so it can rotate with real perspective. It is split into its outer
 * face (in front of the letter) and inner face (behind it), each covering
 * half of the 180deg turn, so the stacking order changes while the flap is
 * edge-on and invisible. Under prefers-reduced-motion it rests open.
 */
const sparkles = [
    { x: 54, y: 84, scale: 0.9, delay: '0.3s' },
    { x: 272, y: 70, scale: 0.65, delay: '1.1s' },
    { x: 236, y: 22, scale: 0.5, delay: '1.9s' },
];
</script>

<template>
    <div class="envelope relative h-[300px] w-80" aria-hidden="true">
        <svg viewBox="0 0 320 300" class="layer overflow-visible">
            <ellipse cx="160" cy="282" rx="100" ry="10" class="envelope-shadow fill-primary-950/50" />
            <g
                v-for="(sparkle, i) in sparkles"
                :key="i"
                :transform="`translate(${sparkle.x} ${sparkle.y}) scale(${sparkle.scale})`"
            >
                <path
                    class="sparkle fill-primary-200"
                    :style="{ animationDelay: sparkle.delay }"
                    d="M0 -12 C1.5 -3 3 -1.5 12 0 C3 1.5 1.5 3 0 12 C-1.5 3 -3 1.5 -12 0 C-3 -1.5 -1.5 -3 0 -12Z"
                />
            </g>
        </svg>

        <div class="envelope-float layer">
            <div class="envelope-bump layer">
                <svg viewBox="0 0 320 300" class="layer overflow-visible">
                    <path d="M64 120 H256 Q264 120 264 128 V244 Q264 260 248 260 H72 Q56 260 56 244 V128 Q56 120 64 120 Z" class="fill-primary-700" />
                </svg>

                <div class="flap flap-inner bg-primary-600"></div>

                <svg viewBox="0 0 320 300" class="layer overflow-visible">
                    <defs>
                        <clipPath id="auth-envelope-letter-clip">
                            <rect x="40" y="-40" width="240" height="280" />
                        </clipPath>
                    </defs>
                    <g clip-path="url(#auth-envelope-letter-clip)">
                        <g class="letter">
                            <rect x="78" y="72" width="164" height="118" rx="10" class="fill-white" />
                            <circle cx="112" cy="102" r="11" fill="none" stroke-width="6" class="stroke-amber-400" />
                            <rect x="121" y="99" width="44" height="6" rx="3" class="fill-amber-400" />
                            <rect x="151" y="105" width="6" height="9" rx="2" class="fill-amber-400" />
                            <rect x="160" y="105" width="5" height="6" rx="2" class="fill-amber-400" />
                            <rect x="98" y="132" width="124" height="7" rx="3.5" class="fill-gray-200" />
                            <rect x="98" y="148" width="92" height="7" rx="3.5" class="fill-gray-200" />
                            <rect x="98" y="164" width="108" height="7" rx="3.5" class="fill-gray-200" />
                        </g>
                    </g>
                </svg>

                <svg viewBox="0 0 320 300" class="layer overflow-visible">
                    <path d="M56 138 L148 196.5 Q160 205 172 196.5 L264 138 V244 Q264 260 248 260 H72 Q56 260 56 244 Z" class="fill-primary-600" />
                    <path d="M56 244 L134 190 M264 244 L186 190" fill="none" stroke-width="2" stroke-linecap="round" class="stroke-primary-700/60" />
                </svg>

                <div class="flap flap-outer bg-primary-500"></div>

                <svg viewBox="0 0 320 300" class="layer overflow-visible">
                    <g class="seal">
                        <circle cx="160" cy="182" r="11" class="fill-amber-400" />
                        <circle cx="160" cy="182" r="6" class="fill-amber-300" />
                    </g>
                </svg>
            </div>
        </div>
    </div>
</template>

<style scoped>
.layer {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
}

.flap {
    position: absolute;
    left: 56px;
    width: 208px;
    backface-visibility: hidden;
}

/* Outer face: hangs down from the top edge when closed. */
.flap-outer {
    top: 120px;
    height: 78px;
    clip-path: path('M8 0 H200 Q208 0 202 5 L122 66 Q104 84 86 66 L6 5 Q0 0 8 0 Z');
    transform-origin: 50% 0;
    transform: perspective(520px) rotateX(90deg);
}

/* Inner face: stands up from the top edge when open. */
.flap-inner {
    top: 49px;
    height: 72px;
    clip-path: path('M6 67 L86 10 Q104 -6 122 10 L202 67 Q208 72 200 72 H8 Q0 72 6 67 Z');
    transform-origin: 50% 100%;
}

.envelope :is(.envelope-shadow, .letter, .seal, .sparkle) {
    transform-box: fill-box;
    transform-origin: center;
}

.envelope-bump {
    transform-origin: 50% 87%;
}

.seal {
    transform: scale(0);
}

.sparkle {
    opacity: 0.6;
}

@media (prefers-reduced-motion: no-preference) {
    .envelope-float {
        animation: float 6s ease-in-out infinite alternate;
    }

    .envelope-shadow {
        animation: shadow 6s ease-in-out infinite alternate;
    }

    .envelope-bump {
        animation: bump 6s infinite both;
    }

    .flap-outer {
        animation: flap-outer 6s infinite both;
    }

    .flap-inner {
        animation: flap-inner 6s infinite both;
    }

    .letter {
        animation: letter 6s infinite both;
    }

    .seal {
        animation: seal 6s infinite both;
    }

    .sparkle {
        animation: twinkle 2.4s ease-in-out infinite both;
    }
}

@keyframes float {
    from { transform: translateY(0); }
    to { transform: translateY(-10px); }
}

@keyframes shadow {
    from { transform: scaleX(1); opacity: 1; }
    to { transform: scaleX(0.86); opacity: 0.65; }
}

/* Opening: outer face lifts 0 -> 90deg, then inner face 90 -> 0deg.
   Closing mirrors it. Light rises as the flap faces up. */
@keyframes flap-outer {
    0%, 11% { transform: perspective(520px) rotateX(0deg); filter: brightness(1); animation-timing-function: cubic-bezier(0.45, 0, 0.9, 0.6); }
    19% { transform: perspective(520px) rotateX(90deg); filter: brightness(1.25); }
    84% { transform: perspective(520px) rotateX(90deg); filter: brightness(1.25); animation-timing-function: cubic-bezier(0.1, 0.4, 0.55, 1); }
    92%, 100% { transform: perspective(520px) rotateX(0deg); filter: brightness(1); }
}

@keyframes flap-inner {
    0%, 19% { transform: perspective(520px) rotateX(-90deg); filter: brightness(1.3); animation-timing-function: cubic-bezier(0.1, 0.4, 0.55, 1); }
    27% { transform: perspective(520px) rotateX(0deg); filter: brightness(1); }
    76% { transform: perspective(520px) rotateX(0deg); filter: brightness(1); animation-timing-function: cubic-bezier(0.45, 0, 0.9, 0.6); }
    84%, 100% { transform: perspective(520px) rotateX(-90deg); filter: brightness(1.3); }
}

@keyframes letter {
    0%, 27% { transform: translateY(140px); animation-timing-function: cubic-bezier(0.22, 1, 0.36, 1); }
    45% { transform: translateY(0); animation-timing-function: ease-in-out; }
    53% { transform: translateY(-6px); animation-timing-function: ease-in-out; }
    61% { transform: translateY(0); animation-timing-function: cubic-bezier(0.55, 0, 0.45, 1); }
    76%, 100% { transform: translateY(140px); }
}

@keyframes seal {
    0%, 7% { transform: scale(1); animation-timing-function: ease-in; }
    10%, 92% { transform: scale(0); animation-timing-function: cubic-bezier(0.34, 1.56, 0.64, 1); }
    96%, 100% { transform: scale(1); }
}

@keyframes bump {
    0%, 91.5% { transform: translateY(0) scaleY(1); animation-timing-function: ease-out; }
    93% { transform: translateY(2px) scaleY(0.985); animation-timing-function: ease-out; }
    96%, 100% { transform: translateY(0) scaleY(1); }
}

@keyframes twinkle {
    0%, 100% { transform: scale(0.4); opacity: 0; }
    50% { transform: scale(1); opacity: 0.9; }
}
</style>
