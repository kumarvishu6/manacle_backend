<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manacle — Staff Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Fraunces', 'serif'],
                    },
                    colors: {
                        paper: '#FAF8F4',
                        bottle: '#24463A',
                        brass: '#A67C3D',
                        clay: '#A64B3A',
                        line: '#DCD6C8',
                    },
                },
            },
        }
    </script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-paper font-sans text-stone-800" x-data="dashboardApp()" x-init="init()">

    <!-- LOGIN SCREEN -->
    <div x-show="!token" class="min-h-screen flex items-center justify-center px-4">
        <div class="bg-white p-8 w-full max-w-sm border border-line rounded-lg">
            <p class="text-sm text-stone-500 mb-1">Manacle</p>
            <h1 class="font-display text-2xl font-semibold text-bottle mb-6">Staff sign in</h1>

            <template x-if="!otpSent">
                <div>
                    <label class="text-sm font-medium text-stone-600">Phone number</label>
                    <input x-model="phone" type="text" placeholder="9876543210"
                        class="w-full mt-1 mb-4 px-3 py-2 border border-line rounded-md focus:outline-none focus:ring-2 focus:ring-brass">
                    <button @click="sendOtp()" :disabled="loading"
                        class="w-full bg-bottle text-white py-2 rounded-md font-medium hover:bg-bottle/90 disabled:opacity-50">
                        <span x-text="loading ? 'Sending…' : 'Send code'"></span>
                    </button>
                </div>
            </template>

            <template x-if="otpSent">
                <div>
                    <label class="text-sm font-medium text-stone-600">Enter code</label>
                    <input x-model="otp" type="text" placeholder="6-digit code"
                        class="w-full mt-1 mb-2 px-3 py-2 border border-line rounded-md focus:outline-none focus:ring-2 focus:ring-brass">
                    <p x-show="devOtp" class="text-xs text-brass mb-4">
                        Dev mode — code: <span class="font-semibold" x-text="devOtp"></span>
                    </p>
                    <button @click="verifyOtp()" :disabled="loading"
                        class="w-full bg-bottle text-white py-2 rounded-md font-medium hover:bg-bottle/90 disabled:opacity-50">
                        <span x-text="loading ? 'Verifying…' : 'Sign in'"></span>
                    </button>
                </div>
            </template>

            <p x-show="error" x-text="error" class="text-clay text-sm mt-4"></p>
        </div>
    </div>

    <!-- DASHBOARD SCREEN -->
    <div x-show="token" x-cloak>
        <header class="border-b border-line px-6 py-5 flex items-center justify-between max-w-4xl mx-auto">
            <div>
                <p class="text-sm text-stone-500">Manacle</p>
                <h1 class="font-display text-2xl font-semibold text-bottle" x-text="selectedSalon ? selectedSalon.name : 'Loading…'"></h1>
            </div>
            <button @click="logout()" class="text-sm text-stone-500 hover:text-clay">Sign out</button>
        </header>

        <main class="px-6 py-8 max-w-4xl mx-auto">

            <!-- Chairs -->
            <div class="flex items-baseline justify-between mb-4">
                <h2 class="text-sm font-medium text-stone-600">Chairs</h2>
                <button x-show="canManageStaff" @click="openAddChairForm()" class="text-sm bg-bottle text-white px-3 py-1.5 rounded-md hover:bg-bottle/90">
                    Add chair
                </button>
            </div>
            <template x-if="chairs.length === 0">
                <p class="text-sm text-stone-400 mb-10">No chairs yet. Add one to start seating customers.</p>
            </template>
            <div class="flex flex-wrap gap-4 mb-10">
                <template x-for="chair in chairs" :key="chair.id">
                    <div class="border border-line rounded-lg p-4 w-56">
                        <div class="flex items-center justify-between mb-3">
                            <span class="font-medium text-stone-800" x-text="chair.label"></span>
                            <span :class="chair.status === 'idle' ? 'bg-bottle/10 text-bottle' : 'bg-clay/10 text-clay'"
                                class="text-xs px-2 py-0.5 rounded" x-text="chair.status"></span>
                        </div>

                        <template x-if="chair.status === 'occupied'">
                            <div>
                                <p class="text-sm text-stone-700" x-text="currentBookingFor(chair)?.customer?.name || 'Customer'"></p>
                                <p class="text-xs text-stone-400 mb-3" x-text="currentBookingFor(chair)?.service?.name"></p>
                                <button @click="completeBooking(currentBookingFor(chair).id)"
                                    class="w-full bg-bottle text-white text-sm py-1.5 rounded-md hover:bg-bottle/90">
                                    Mark done
                                </button>
                            </div>
                        </template>

                        <template x-if="chair.status === 'idle'">
                            <div>
                                <template x-if="nextWaiting()">
                                    <button @click="startBooking(nextWaiting().id, chair.id)"
                                        class="w-full bg-brass text-white text-sm py-1.5 rounded-md hover:bg-brass/90">
                                        Start: <span x-text="nextWaiting()?.customer?.name"></span>
                                    </button>
                                </template>
                                <template x-if="!nextWaiting()">
                                    <p class="text-sm text-stone-400">No one waiting</p>
                                </template>
                            </div>
                        </template>

                        <button x-show="canManageStaff && chair.status === 'idle'" @click="removeChair(chair.id)"
                            class="text-xs text-stone-400 hover:text-clay mt-3">
                            Remove chair
                        </button>
                    </div>
                </template>
            </div>

            <!-- Queue -->
            <div class="flex items-baseline justify-between mb-4">
                <h2 class="text-sm font-medium text-stone-600">Waiting queue</h2>
                <button @click="openWalkInForm()" class="text-sm bg-bottle text-white px-3 py-1.5 rounded-md hover:bg-bottle/90">
                    Add walk-in
                </button>
            </div>
            <div class="border-t border-line mb-10">
                <template x-if="waitingList().length === 0">
                    <p class="text-sm text-stone-400 py-6">No one in the queue right now. Add a walk-in to get started.</p>
                </template>
                <template x-for="(booking, index) in waitingList()" :key="booking.id">
                    <div class="flex items-baseline gap-4 py-4 border-b border-line">
                        <span class="font-display text-2xl tabular-nums text-brass w-8 text-right" x-text="index + 1"></span>
                        <div class="flex-1">
                            <p class="font-medium text-stone-800" x-text="booking.customer?.name"></p>
                            <p class="text-sm text-stone-500" x-text="booking.service?.name"></p>
                            <p class="text-xs text-stone-400" x-text="booking.customer?.phone || ''"></p>
                        </div>
                        <button @click="noShow(booking.id)" class="text-xs text-stone-400 hover:text-clay">
                            No-show
                        </button>
                    </div>
                </template>
            </div>

            <!-- Staff -->
            <template x-if="canManageStaff">
                <div>
                    <div class="flex items-baseline justify-between mb-4">
                        <h2 class="text-sm font-medium text-stone-600">Staff</h2>
                        <button @click="openAddStaffForm()" class="text-sm bg-bottle text-white px-3 py-1.5 rounded-md hover:bg-bottle/90">
                            Add staff
                        </button>
                    </div>
                    <div class="border-t border-line">
                        <template x-if="staffList.length === 0">
                            <p class="text-sm text-stone-400 py-6">No staff added yet. Add someone to let them run this salon independently.</p>
                        </template>
                        <template x-for="member in staffList" :key="member.id">
                            <div class="flex items-center justify-between py-4 border-b border-line">
                                <div>
                                    <p class="font-medium text-stone-800" x-text="member.user?.name"></p>
                                    <p class="text-sm text-stone-500" x-text="member.user?.phone"></p>
                                </div>
                                <button @click="removeStaff(member.id)" class="text-xs text-stone-400 hover:text-clay">
                                    Remove
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </main>
    </div>

    <!-- ADD WALK-IN MODAL -->
    <div x-show="showWalkInForm" x-cloak class="fixed inset-0 bg-bottle/20 flex items-center justify-center px-4 z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-sm border border-line">
            <h3 class="font-display text-lg font-semibold text-bottle mb-4">Add walk-in</h3>

            <label class="text-sm font-medium text-stone-600">Name</label>
            <input x-model="walkInName" type="text" placeholder="Customer name"
                class="w-full mt-1 mb-3 px-3 py-2 border border-line rounded-md focus:outline-none focus:ring-2 focus:ring-brass">

            <label class="text-sm font-medium text-stone-600">Phone (optional)</label>
            <input x-model="walkInPhone" type="text" placeholder="9876543210"
                class="w-full mt-1 mb-3 px-3 py-2 border border-line rounded-md focus:outline-none focus:ring-2 focus:ring-brass">

            <label class="text-sm font-medium text-stone-600">Service</label>
            <select x-model="walkInServiceId"
                class="w-full mt-1 mb-4 px-3 py-2 border border-line rounded-md focus:outline-none focus:ring-2 focus:ring-brass">
                <option value="" disabled selected>Select a service</option>
                <template x-for="service in services" :key="service.id">
                    <option :value="service.id" x-text="service.name + ' — ₹' + service.price"></option>
                </template>
            </select>

            <p x-show="walkInError" x-text="walkInError" class="text-clay text-xs mb-3"></p>

            <div class="flex gap-2">
                <button @click="closeWalkInForm()"
                    class="flex-1 border border-line text-stone-700 py-2 rounded-md hover:border-stone-400">
                    Cancel
                </button>
                <button @click="submitWalkIn()" :disabled="walkInLoading"
                    class="flex-1 bg-bottle text-white py-2 rounded-md hover:bg-bottle/90 disabled:opacity-50">
                    <span x-text="walkInLoading ? 'Adding…' : 'Add'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ADD STAFF MODAL -->
    <div x-show="showAddStaffForm" x-cloak class="fixed inset-0 bg-bottle/20 flex items-center justify-center px-4 z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-sm border border-line">
            <h3 class="font-display text-lg font-semibold text-bottle mb-1">Add staff</h3>
            <p class="text-sm text-stone-500 mb-4">They'll sign in with this phone number using the same code you just used.</p>

            <label class="text-sm font-medium text-stone-600">Name</label>
            <input x-model="staffName" type="text" placeholder="Staff member's name"
                class="w-full mt-1 mb-3 px-3 py-2 border border-line rounded-md focus:outline-none focus:ring-2 focus:ring-brass">

            <label class="text-sm font-medium text-stone-600">Phone</label>
            <input x-model="staffPhone" type="text" placeholder="9876543210"
                class="w-full mt-1 mb-4 px-3 py-2 border border-line rounded-md focus:outline-none focus:ring-2 focus:ring-brass">

            <p x-show="staffError" x-text="staffError" class="text-clay text-xs mb-3"></p>

            <div class="flex gap-2">
                <button @click="closeAddStaffForm()"
                    class="flex-1 border border-line text-stone-700 py-2 rounded-md hover:border-stone-400">
                    Cancel
                </button>
                <button @click="submitAddStaff()" :disabled="staffLoading"
                    class="flex-1 bg-bottle text-white py-2 rounded-md hover:bg-bottle/90 disabled:opacity-50">
                    <span x-text="staffLoading ? 'Adding…' : 'Add'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ADD CHAIR MODAL -->
    <div x-show="showAddChairForm" x-cloak class="fixed inset-0 bg-bottle/20 flex items-center justify-center px-4 z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-sm border border-line">
            <h3 class="font-display text-lg font-semibold text-bottle mb-4">Add chair</h3>

            <label class="text-sm font-medium text-stone-600">Label</label>
            <input x-model="chairLabel" type="text" placeholder="e.g. Chair 2"
                class="w-full mt-1 mb-4 px-3 py-2 border border-line rounded-md focus:outline-none focus:ring-2 focus:ring-brass">

            <p x-show="chairError" x-text="chairError" class="text-clay text-xs mb-3"></p>

            <div class="flex gap-2">
                <button @click="closeAddChairForm()"
                    class="flex-1 border border-line text-stone-700 py-2 rounded-md hover:border-stone-400">
                    Cancel
                </button>
                <button @click="submitAddChair()" :disabled="chairLoading"
                    class="flex-1 bg-bottle text-white py-2 rounded-md hover:bg-bottle/90 disabled:opacity-50">
                    <span x-text="chairLoading ? 'Adding…' : 'Add'"></span>
                </button>
            </div>
        </div>
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
        services: [],
        userRole: null,
        pollTimer: null,

        showWalkInForm: false,
        walkInName: '',
        walkInPhone: '',
        walkInServiceId: '',
        walkInLoading: false,
        walkInError: null,

        staffList: [],
        showAddStaffForm: false,
        staffName: '',
        staffPhone: '',
        staffLoading: false,
        staffError: null,

        showAddChairForm: false,
        chairLabel: '',
        chairLoading: false,
        chairError: null,

        get canManageStaff() {
            return this.userRole === 'salon_owner' || this.userRole === 'super_admin';
        },

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
                if (!res.ok) throw new Error(data.message || 'Failed to send code');
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
                if (!res.ok) throw new Error(data.message || 'Invalid code');
                this.token = data.token;
                this.userRole = data.user?.role || null;
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
                this.loadServices();
                this.loadQueueData();
                if (this.canManageStaff) this.loadStaff();
                this.pollTimer = setInterval(() => this.loadQueueData(), 15000);
            }
        },

        async loadServices() {
            if (!this.selectedSalon) return;
            const res = await fetch(`/api/salons/${this.selectedSalon.id}/services`, { headers: this.headers() });
            this.services = await res.json();
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

        async loadStaff() {
            if (!this.selectedSalon) return;
            try {
                const res = await fetch(`/api/salons/${this.selectedSalon.id}/staff`, { headers: this.headers() });
                if (res.ok) this.staffList = await res.json();
            } catch (e) { /* silently skip if not permitted */ }
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

        openWalkInForm() {
            this.walkInName = '';
            this.walkInPhone = '';
            this.walkInServiceId = this.services.length === 1 ? this.services[0].id : '';
            this.walkInError = null;
            this.showWalkInForm = true;
        },

        closeWalkInForm() {
            this.showWalkInForm = false;
        },

        async submitWalkIn() {
            this.walkInError = null;
            if (!this.walkInName.trim()) { this.walkInError = 'Name is required.'; return; }
            if (!this.walkInServiceId) { this.walkInError = 'Please select a service.'; return; }

            this.walkInLoading = true;
            try {
                const res = await fetch(`/api/salons/${this.selectedSalon.id}/walk-ins`, {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({
                        name: this.walkInName,
                        phone: this.walkInPhone || null,
                        service_id: this.walkInServiceId,
                    }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Failed to add walk-in');
                this.showWalkInForm = false;
                this.loadQueueData();
            } catch (e) {
                this.walkInError = e.message;
            }
            this.walkInLoading = false;
        },

        openAddStaffForm() {
            this.staffName = '';
            this.staffPhone = '';
            this.staffError = null;
            this.showAddStaffForm = true;
        },

        closeAddStaffForm() {
            this.showAddStaffForm = false;
        },

        async submitAddStaff() {
            this.staffError = null;
            if (!this.staffName.trim()) { this.staffError = 'Name is required.'; return; }
            if (!this.staffPhone.trim()) { this.staffError = 'Phone number is required.'; return; }

            this.staffLoading = true;
            try {
                const res = await fetch(`/api/salons/${this.selectedSalon.id}/staff`, {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({
                        name: this.staffName,
                        phone: this.staffPhone,
                    }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Failed to add staff');
                this.showAddStaffForm = false;
                this.loadStaff();
            } catch (e) {
                this.staffError = e.message;
            }
            this.staffLoading = false;
        },

        async removeStaff(staffId) {
            await fetch(`/api/staff/${staffId}`, {
                method: 'DELETE', headers: this.headers(),
            });
            this.loadStaff();
        },

        openAddChairForm() {
            this.chairLabel = '';
            this.chairError = null;
            this.showAddChairForm = true;
        },

        closeAddChairForm() {
            this.showAddChairForm = false;
        },

        async submitAddChair() {
            this.chairError = null;
            if (!this.chairLabel.trim()) { this.chairError = 'Label is required.'; return; }

            this.chairLoading = true;
            try {
                const res = await fetch(`/api/salons/${this.selectedSalon.id}/chairs`, {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({ label: this.chairLabel }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Failed to add chair');
                this.showAddChairForm = false;
                this.loadQueueData();
            } catch (e) {
                this.chairError = e.message;
            }
            this.chairLoading = false;
        },

        async removeChair(chairId) {
            await fetch(`/api/chairs/${chairId}`, {
                method: 'DELETE', headers: this.headers(),
            });
            this.loadQueueData();
        },
    }
}
</script>

</body>
</html>