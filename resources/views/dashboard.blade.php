<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manacle — Staff Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] } } }
        }
    </script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-50 font-sans text-gray-800" x-data="dashboardApp()" x-init="init()">

    <!-- LOGIN SCREEN -->
    <div x-show="!token" class="min-h-screen flex items-center justify-center px-4">
        <div class="bg-white shadow-lg rounded-2xl p-8 w-full max-w-sm border border-gray-100">
            <h1 class="text-2xl font-bold mb-1">Manacle</h1>
            <p class="text-gray-500 text-sm mb-6">Staff / Owner Dashboard</p>

            <template x-if="!otpSent">
                <div>
                    <label class="text-sm font-medium text-gray-600">Phone Number</label>
                    <input x-model="phone" type="text" placeholder="9876543210"
                        class="w-full mt-1 mb-4 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-black">
                    <button @click="sendOtp()" :disabled="loading"
                        class="w-full bg-black text-white py-2 rounded-lg font-medium hover:bg-gray-800 disabled:opacity-50">
                        <span x-text="loading ? 'Sending...' : 'Send OTP'"></span>
                    </button>
                </div>
            </template>

            <template x-if="otpSent">
                <div>
                    <label class="text-sm font-medium text-gray-600">Enter OTP</label>
                    <input x-model="otp" type="text" placeholder="6-digit code"
                        class="w-full mt-1 mb-2 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-black">
                    <p x-show="devOtp" class="text-xs text-amber-600 mb-4">
                        Dev mode — OTP: <span class="font-semibold" x-text="devOtp"></span>
                    </p>
                    <button @click="verifyOtp()" :disabled="loading"
                        class="w-full bg-black text-white py-2 rounded-lg font-medium hover:bg-gray-800 disabled:opacity-50">
                        <span x-text="loading ? 'Verifying...' : 'Verify & Login'"></span>
                    </button>
                </div>
            </template>

            <p x-show="error" x-text="error" class="text-red-500 text-sm mt-4"></p>
        </div>
    </div>

    <!-- DASHBOARD SCREEN -->
    <div x-show="token" x-cloak>
        <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-lg font-bold">Manacle Dashboard</h1>
                <p class="text-sm text-gray-500" x-text="selectedSalon ? selectedSalon.name : 'Loading salon...'"></p>
            </div>
            <button @click="logout()" class="text-sm text-gray-500 hover:text-red-500">Logout</button>
        </header>

        <main class="p-6 max-w-5xl mx-auto">
            <!-- Chairs -->
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Chairs</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                <template x-for="chair in chairs" :key="chair.id">
                    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-semibold" x-text="chair.label"></span>
                            <span :class="chair.status === 'idle' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                class="text-xs px-2 py-0.5 rounded-full font-medium" x-text="chair.status"></span>
                        </div>

                        <template x-if="chair.status === 'occupied'">
                            <div>
                                <p class="text-sm text-gray-600" x-text="currentBookingFor(chair)?.customer?.name || 'Customer'"></p>
                                <p class="text-xs text-gray-400 mb-3" x-text="currentBookingFor(chair)?.service?.name"></p>
                                <button @click="completeBooking(currentBookingFor(chair).id)"
                                    class="w-full bg-black text-white text-sm py-1.5 rounded-lg hover:bg-gray-800">
                                    Mark Done
                                </button>
                            </div>
                        </template>

                        <template x-if="chair.status === 'idle'">
                            <div>
                                <template x-if="nextWaiting()">
                                    <button @click="startBooking(nextWaiting().id, chair.id)"
                                        class="w-full bg-green-600 text-white text-sm py-1.5 rounded-lg hover:bg-green-700">
                                        Start: <span x-text="nextWaiting()?.customer?.name"></span>
                                    </button>
                                </template>
                                <template x-if="!nextWaiting()">
                                    <p class="text-sm text-gray-400">No one waiting</p>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Queue -->
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Waiting Queue</h2>
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <template x-if="waitingList().length === 0">
                    <p class="text-sm text-gray-400 p-4">No one in the queue right now.</p>
                </template>
                <template x-for="(booking, index) in waitingList()" :key="booking.id">
                    <div class="flex items-center justify-between px-4 py-3 border-b last:border-b-0 border-gray-100">
                        <div>
                            <p class="font-medium text-sm">
                                <span x-text="index + 1"></span>. <span x-text="booking.customer?.name"></span>
                            </p>
                            <p class="text-xs text-gray-400" x-text="booking.service?.name + ' · ' + (booking.customer?.phone || '')"></p>
                        </div>
                        <button @click="noShow(booking.id)" class="text-xs text-gray-400 hover:text-red-500">
                            No-show
                        </button>
                    </div>
                </template>
            </div>
        </main>
    </div>

<script>
function dashboardApp() {
    return {
        token: null,
        phone: '',
        otp: '',
        otpSent: false,
        devOtp: null,
        loading: false,
        error: null,
        salons: [],
        selectedSalon: null,
        chairs: [],
        queue: [],
        pollTimer: null,

        init() {
            this.token = sessionStorage.getItem('manacle_token');
            if (this.token) this.loadSalons();
        },

        headers() {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${this.token}`,
            };
        },

        async sendOtp() {
            this.loading = true; this.error = null;
            try {
                const res = await fetch('/api/auth/send-otp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ phone: this.phone }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Failed to send OTP');
                this.devOtp = data.dev_otp;
                this.otpSent = true;
            } catch (e) { this.error = e.message; }
            this.loading = false;
        },

        async verifyOtp() {
            this.loading = true; this.error = null;
            try {
                const res = await fetch('/api/auth/verify-otp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ phone: this.phone, otp: this.otp }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Invalid OTP');
                this.token = data.token;
                sessionStorage.setItem('manacle_token', this.token);
                this.loadSalons();
            } catch (e) { this.error = e.message; }
            this.loading = false;
        },

        logout() {
            sessionStorage.removeItem('manacle_token');
            this.token = null;
            this.phone = ''; this.otp = ''; this.otpSent = false;
            clearInterval(this.pollTimer);
        },

        async loadSalons() {
            const res = await fetch('/api/salons', { headers: this.headers() });
            const data = await res.json();
            this.salons = data;
            if (data.length > 0) {
                this.selectedSalon = data[0];
                this.loadQueueData();
                this.pollTimer = setInterval(() => this.loadQueueData(), 15000);
            }
        },

        async loadQueueData() {
            if (!this.selectedSalon) return;
            const [chairsRes, queueRes] = await Promise.all([
                fetch(`/api/salons/${this.selectedSalon.id}/chairs`, { headers: this.headers() }),
                fetch(`/api/salons/${this.selectedSalon.id}/bookings`, { headers: this.headers() }),
            ]);
            this.chairs = await chairsRes.json();
            this.queue = await queueRes.json();
        },

        currentBookingFor(chair) {
            return this.queue.find(b => b.id === chair.current_booking_id);
        },

        waitingList() {
            return this.queue.filter(b => b.status === 'waiting');
        },

        nextWaiting() {
            return this.waitingList()[0] || null;
        },

        async startBooking(bookingId, chairId) {
            await fetch(`/api/bookings/${bookingId}/start`, {
                method: 'POST', headers: this.headers(),
                body: JSON.stringify({ chair_id: chairId }),
            });
            this.loadQueueData();
        },

        async completeBooking(bookingId) {
            await fetch(`/api/bookings/${bookingId}/complete`, {
                method: 'POST', headers: this.headers(),
            });
            this.loadQueueData();
        },

        async noShow(bookingId) {
            await fetch(`/api/bookings/${bookingId}/no-show`, {
                method: 'POST', headers: this.headers(),
            });
            this.loadQueueData();
        },
    }
}
</script>

</body>
</html>