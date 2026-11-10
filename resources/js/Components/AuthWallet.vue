<script setup>
/**
 * Decorative animated wallet for the auth brand panel. Coins drop into the
 * wallet on a loop; the wallet floats and gives a small bump as each coin
 * lands. Every move is transform/opacity only and is disabled under
 * prefers-reduced-motion, leaving a static composition.
 */
const coins = [
    { x: 128, delay: '0s', tilt: '-14deg' },
    { x: 166, delay: '1.2s', tilt: '10deg' },
    { x: 146, delay: '2.4s', tilt: '-4deg' },
];

const sparkles = [
    { x: 58, y: 70, scale: 1, delay: '0s' },
    { x: 268, y: 92, scale: 0.7, delay: '0.8s' },
    { x: 246, y: 30, scale: 0.55, delay: '1.6s' },
];
</script>

<template>
    <svg viewBox="0 0 320 300" class="wallet w-80 overflow-visible" aria-hidden="true">
        <ellipse cx="160" cy="282" rx="104" ry="10" class="wallet-shadow fill-primary-950/50" />

        <g
            v-for="(sparkle, i) in sparkles"
            :key="`s${i}`"
            :transform="`translate(${sparkle.x} ${sparkle.y}) scale(${sparkle.scale})`"
        >
            <path
                class="sparkle fill-primary-200"
                :style="{ animationDelay: sparkle.delay }"
                d="M0 -12 C1.5 -3 3 -1.5 12 0 C3 1.5 1.5 3 0 12 C-1.5 3 -3 1.5 -12 0 C-3 -1.5 -1.5 -3 0 -12Z"
            />
        </g>

        <g class="wallet-float">
            <g class="wallet-bump">
                <rect x="48" y="112" width="224" height="150" rx="24" class="fill-primary-700" />

                <g class="card-a">
                    <g transform="rotate(-7 150 130)">
                        <rect x="74" y="80" width="152" height="94" rx="12" class="fill-primary-300" />
                        <rect x="74" y="98" width="152" height="16" class="fill-primary-400" />
                    </g>
                </g>
                <g class="card-b">
                    <g transform="rotate(5 176 140)">
                        <rect x="98" y="92" width="152" height="94" rx="12" class="fill-white" />
                        <rect x="114" y="108" width="26" height="20" rx="5" class="fill-amber-300" />
                        <rect x="114" y="140" width="72" height="7" rx="3.5" class="fill-gray-200" />
                    </g>
                </g>

                <g v-for="(coin, i) in coins" :key="`c${i}`" :transform="`translate(${coin.x} 0)`">
                    <g class="coin" :style="{ animationDelay: coin.delay, '--tilt': coin.tilt }">
                        <circle cx="0" cy="34" r="17" class="fill-amber-400" />
                        <circle cx="0" cy="34" r="12.5" class="fill-amber-300" />
                        <text x="0" y="39.5" text-anchor="middle" class="fill-amber-700 text-[15px] font-bold">$</text>
                    </g>
                </g>

                <rect x="48" y="140" width="224" height="122" rx="24" class="fill-primary-600" />
                <rect x="48" y="140" width="224" height="34" rx="24" class="fill-primary-500" />
                <rect x="48" y="158" width="224" height="16" class="fill-primary-500" />
                <rect
                    x="60"
                    y="152"
                    width="200"
                    height="98"
                    rx="16"
                    fill="none"
                    stroke-width="2"
                    stroke-dasharray="5 5"
                    class="stroke-primary-300/70"
                />

                <rect x="214" y="180" width="74" height="46" rx="16" class="fill-primary-800" />
                <circle cx="238" cy="203" r="8" class="clasp fill-primary-200" />
            </g>
        </g>
    </svg>
</template>

<style scoped>
.wallet :is(.wallet-float, .wallet-bump, .card-a, .card-b, .coin, .sparkle, .wallet-shadow, .clasp) {
    transform-box: fill-box;
    transform-origin: center;
}

.wallet-bump {
    transform-origin: center bottom;
}

.coin {
    transform: rotate(var(--tilt));
}

.sparkle {
    opacity: 0.6;
}

@media (prefers-reduced-motion: no-preference) {
    .wallet-float {
        animation: float 6s ease-in-out infinite alternate;
    }

    .wallet-shadow {
        animation: shadow 6s ease-in-out infinite alternate;
    }

    .wallet-bump {
        animation: bump 1.2s ease-out infinite;
    }

    .card-a {
        animation: peek 4.8s ease-in-out infinite alternate;
    }

    .card-b {
        animation: peek 4.8s ease-in-out -2.4s infinite alternate;
    }

    .coin {
        animation: drop 3.6s infinite both;
    }

    .sparkle {
        animation: twinkle 2.4s ease-in-out infinite both;
    }

    .clasp {
        animation: glint 3.6s ease-in-out infinite;
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

@keyframes bump {
    0%, 52% { transform: translateY(0) scaleY(1); }
    58% { transform: translateY(3px) scaleY(0.985); }
    72%, 100% { transform: translateY(0) scaleY(1); }
}

@keyframes peek {
    from { transform: translateY(0); }
    to { transform: translateY(-8px); }
}

@keyframes drop {
    0% { transform: translateY(-50px) rotate(var(--tilt)); opacity: 0; animation-timing-function: ease-out; }
    14% { transform: translateY(-30px) rotate(var(--tilt)); opacity: 1; animation-timing-function: cubic-bezier(0.55, 0, 1, 0.45); }
    56% { transform: translateY(128px) rotate(0deg); opacity: 1; }
    60%, 100% { transform: translateY(132px) rotate(0deg); opacity: 0; }
}

@keyframes twinkle {
    0%, 100% { transform: scale(0.4); opacity: 0; }
    50% { transform: scale(1); opacity: 0.9; }
}

@keyframes glint {
    0%, 80%, 100% { opacity: 1; }
    88% { opacity: 0.5; }
}
</style>
