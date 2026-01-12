<script setup>
import { onMounted, ref, computed } from "vue";
import { api } from "./api";

// --------------------
// Toast
// --------------------
const toastMessage = ref("");
const toastType = ref("success");

function showToast(msg, type = "success") {
  toastMessage.value = msg;
  toastType.value = type;
  setTimeout(() => (toastMessage.value = ""), 3000);
}

// --------------------
// Tabs
// --------------------
const tab = ref("client");



const today = new Date().toISOString().slice(0, 10);
const date = ref(today);
const services = ref([]);
const slots = ref([]);
const selectedSlot = ref("");
const selectedServiceId = ref("");
const email = ref("");
const loadingClient = ref(false);

const emailError = computed(() => {
  if (!email.value) return "";
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email.value) ? "" : "Please enter a valid email address.";
});

const canBook = computed(() =>
  date.value &&
  selectedSlot.value &&
  selectedServiceId.value &&
  email.value &&
  !emailError.value
);

const DOW_LABELS = {
  0: "Sunday",
  1: "Monday",
  2: "Tuesday",
  3: "Wednesday",
  4: "Thursday",
  5: "Friday",
  6: "Saturday",
};


async function loadServices() {
  services.value = await api.getServices();
  if (services.value.length && !selectedServiceId.value) {
    selectedServiceId.value = String(services.value[0].id);
  }
}

async function loadAvailability() {
  selectedSlot.value = "";
  loadingClient.value = true;
  try {
    const res = await api.getAvailability(date.value);
    slots.value = res.slots || [];
  } catch (e) {
    slots.value = [];
    showToast(e.message, "error");
  } finally {
    loadingClient.value = false;
  }
}

async function book() {
  loadingClient.value = true;
  try {
    await api.createBooking({
      date: date.value,
      start_time: selectedSlot.value,
      service_id: Number(selectedServiceId.value),
      client_email: email.value,
    });
    showToast("Booked successfully.", "success");
    await loadAvailability();
  } catch (e) {
    showToast(e.message, "error");
  } finally {
    loadingClient.value = false;
  }
}

// --------------------
// Admin: weekly calendar (rules)
// --------------------
const rules = ref([]);
const loadingAdmin = ref(false);

const weekDays = [
  { label: "Mon", dow: 1 },
  { label: "Tue", dow: 2 },
  { label: "Wed", dow: 3 },
  { label: "Thu", dow: 4 },
  { label: "Fri", dow: 5 },
  { label: "Sat", dow: 6 },
  { label: "Sun", dow: 0 },
];

// Calendar view window
const dayStartHour = 0;  
const dayEndHour = 23;   
const pixelsPerMinute = 1; 

const gridStepMinutes = 30;

const timeLabels = computed(() => {
  const labels = [];
  for (let h = dayStartHour; h <= dayEndHour; h++) {
    labels.push(String(h).padStart(2, "0") + ":00");
  }
  return labels;
});

function toMinutes(hhmm) {
  const [h, m] = hhmm.split(":").map(Number);
  return h * 60 + m;
}

function normalizeTime(t) {
  // backend might return "18:00:00"
  return String(t).slice(0, 5);
}

function topPx(startHHMM) {
  const minutesFromTop = toMinutes(startHHMM) - dayStartHour * 60;
  return Math.max(0, minutesFromTop) * pixelsPerMinute;
}

function heightPx(startHHMM, endHHMM) {
  const dur = toMinutes(endHHMM) - toMinutes(startHHMM);
  return Math.max(0, dur) * pixelsPerMinute;
}

const calendarHeight = computed(() => (dayEndHour - dayStartHour) * 60 * pixelsPerMinute);

const rulesByDow = computed(() => {
  const map = new Map();
  for (const d of weekDays) map.set(d.dow, []);
  for (const r of rules.value) {
    const dow = Number(r.day_of_week);
    if (!map.has(dow)) map.set(dow, []);
    map.get(dow).push({
      ...r,
      startHHMM: normalizeTime(r.start_time),
      endHHMM: normalizeTime(r.end_time),
    });
  }
  // sort for display
  for (const [k, arr] of map.entries()) {
    arr.sort((a, b) => a.startHHMM.localeCompare(b.startHHMM));
  }
  return map;
});

async function loadRules() {
  loadingAdmin.value = true;
  try {
    rules.value = await api.getWorkRules();
  } catch (e) {
    showToast(e.message, "error");
  } finally {
    loadingAdmin.value = false;
  }
}

// Add rule form
const newRule = ref({
  day_of_week: 1,       // Monday default
  start_time: "18:00",
  end_time: "20:00",
  slot_minutes: 30,
});

async function addRule() {
  try {
    await api.createWorkRule({
      day_of_week: Number(newRule.value.day_of_week),
      start_time: newRule.value.start_time,
      end_time: newRule.value.end_time,
      slot_minutes: Number(newRule.value.slot_minutes),
    });
    showToast("Rule added.", "success");
    await loadRules();
  } catch (e) {
    showToast(e.message, "error");
  }
}

async function deleteRule(id) {
  try {
    await api.deleteWorkRule(id);
    showToast("Rule deleted.", "success");
    await loadRules();
  } catch (e) {
    showToast(e.message, "error");
  }
}

onMounted(async () => {
  // client init
  try {
    await loadServices();
    await loadAvailability();
  } catch (e) {
    showToast(e.message, "error");
  }

  // admin init
  await loadRules();
});
</script>

<template>
  <div class="app">
    <div v-if="toastMessage" class="toast" :class="toastType">
      {{ toastMessage }}
    </div>

    <header class="topbar">
      <h1>Smart Booking Scheduler</h1>
      <nav class="tabs">
        <button class="tab" :class="{ active: tab === 'client' }" @click="tab = 'client'">Client</button>
        <button class="tab" :class="{ active: tab === 'admin' }" @click="tab = 'admin'">Coach</button>
      </nav>
    </header>

    <!-- CLIENT -->
    <section v-if="tab === 'client'" class="card">
      <h2>Book an appointment</h2>

      <div class="form">
        <label>
          Date:
          <input type="date" v-model="date" :min="today" @change="loadAvailability" />
        </label>

        <label>
          Service:
          <select v-model="selectedServiceId">
            <option v-for="s in services" :key="s.id" :value="String(s.id)">
              {{ s.name }}
            </option>
          </select>
        </label>

        <label>
          Email:
          <input type="email" v-model="email" placeholder="you@example.com" required />
        </label>
        <div v-if="emailError" class="hint error">{{ emailError }}</div>

        <div>
          <div class="label">Available times</div>
          <div v-if="loadingClient">Loading…</div>
          <div v-else-if="slots.length === 0" class="hint">No available slots.</div>

          <div v-else class="slotWrap">
            <button
              v-for="t in slots"
              :key="t"
              type="button"
              class="slotBtn"
              :class="{ selected: selectedSlot === t }"
              @click="selectedSlot = t"
            >
              {{ t }}
            </button>
          </div>
        </div>

        <button class="primary" :disabled="!canBook || loadingClient" @click="book" type="button">
          Book
        </button>
      </div>
    </section>

    <!-- ADMIN -->
    <section v-else class="card">
      <h2>Coach schedule rules</h2>

      <div class="adminLayout">
        <!-- Left: Add rule -->
        <div class="panel">
          <h3 color="black">Add rule</h3>

          <div class="form">
            <label>
              Day:
              <select v-model="newRule.day_of_week">
                <option v-for="d in weekDays" :key="d.dow" :value="d.dow">
                  {{ d.label }}
                </option>
              </select>
            </label>

            <label>
              Start time:
              <input type="time" v-model="newRule.start_time" />
            </label>

            <label>
              End time:
              <input type="time" v-model="newRule.end_time" />
            </label>

            <label>
              Slot minutes:
              <input
                type="number"
                min="1"
                step="1"
                v-model.number="newRule.slot_minutes"
              />
            </label>


            <button class="primary" type="button" @click="addRule">Add</button>

            <div class="hint">
              Rules apply to all weeks (this view shows an example week).
            </div>
          </div>

          <h3 style="margin-top: 18px;">Current rules</h3>
          <div v-if="loadingAdmin" class="hint">Loading…</div>

          <div v-else class="ruleList">
            <div v-for="r in rules" :key="r.id" class="ruleRow">
              <div class="ruleText">
                {{ DOW_LABELS[r.day_of_week] }} — {{ String(r.start_time).slice(0,5) }}–{{ String(r.end_time).slice(0,5) }}
                ({{ r.slot_minutes }}m)
              </div>
              <button class="danger" type="button" @click="deleteRule(r.id)">Delete</button>
            </div>
          </div>
        </div>

        <!-- Right: Week calendar -->
        <div class="calendar">
          <div class="calHeader">
            <div class="timeColHeader"></div>
            <div class="dayHeaders">
              <div v-for="d in weekDays" :key="d.dow" class="dayHeader">{{ d.label }}</div>
            </div>
          </div>

          <div class="calBody">
            <!-- time labels column -->
            <div class="timeCol" :style="{ height: calendarHeight + 'px' }">
              <div
                v-for="t in timeLabels"
                :key="t"
                class="timeLabel"
                :style="{ top: (toMinutes(t) - (dayStartHour * 60)) * pixelsPerMinute + 'px' }"
              >
                {{ t }}
              </div>
            </div>

            <!-- days grid -->
            <div class="daysGrid" :style="{ height: calendarHeight + 'px' }">
              <div v-for="d in weekDays" :key="d.dow" class="dayCol">
                <!-- grid lines -->
                <div
                  v-for="i in Math.floor(((dayEndHour - dayStartHour) * 60) / gridStepMinutes)"
                  :key="i"
                  class="gridLine"
                  :style="{ top: (i * gridStepMinutes * pixelsPerMinute) + 'px' }"
                ></div>

                <!-- rule blocks -->
                <div
                  v-for="r in rulesByDow.get(d.dow) || []"
                  :key="r.id"
                  class="ruleBlock"
                  :style="{
                    top: topPx(r.startHHMM) + 'px',
                    height: heightPx(r.startHHMM, r.endHHMM) + 'px'
                  }"
                  :title="`${r.startHHMM}-${r.endHHMM} (${r.slot_minutes}m)`"
                >
                  <div class="ruleTitle">{{ r.startHHMM }}–{{ r.endHHMM }}</div>
                  <div class="ruleSub">{{ r.slot_minutes }} min slots</div>
                </div>
              </div>
            </div>
          </div>

          <div class="hint" style="margin-top: 8px;">
            Visual week view. Rule blocks scale by duration (end - start).
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Layout */
.app {
  max-width: 1300px;
  margin: 24px auto;
  font-family: system-ui;
  padding: 0 12px;
}
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}
.tabs {
  display: flex;
  gap: 8px;
}
.tab {
  padding: 8px 10px;
  border: 1px solid #ccc;
  background: #fff;
  cursor: pointer;
  color: #000;
}
.tab.active {
  background: #eee;
}
.card {
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 14px;
  background: #fff;
}
.form {
  display: grid;
  gap: 10px;
  margin-top: 10px;
}
label {
  display: grid;
  gap: 6px;
}
input, select {
  padding: 8px 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
}
.primary {
  padding: 10px 12px;
  border: 1px solid #333;
  background: #f3f3f3;
  cursor: pointer;
  color: #000;
}
.primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.hint {
  color: #555;
  font-size: 0.92em;
}
.hint.error {
  color: #7a1c1c;
}
.label {
  margin-bottom: 8px;
  font-weight: 600;
}

/* Client slots */
.slotWrap {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.slotBtn {
  padding: 8px 10px;
  border: 1px solid #ccc;
  background: #fff;
  cursor: pointer;
  color: #000;
}
.slotBtn.selected {
  background: #ddd;
}

/* Toast */
.toast {
  position: fixed;
  top: 16px;
  right: 16px;
  padding: 12px 16px;
  border-radius: 6px;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  z-index: 9999;
}
.toast.success {
  background: #e6f4ea;
  color: #0f5132;
  border: 1px solid #b7e1c1;
}
.toast.error {
  background: #fdecec;
  color: #7a1c1c;
  border: 1px solid #f5b5b5;
}

/* Admin layout */
.adminLayout {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 14px;
  align-items: start;
}
.panel {
  border: 1px solid #eee;
  border-radius: 8px;
  padding: 12px;
  background: #fafafa;
}
.ruleList {
  display: grid;
  gap: 8px;
}
.ruleRow {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 8px;
  align-items: center;
  padding: 8px;
  border: 1px solid #eee;
  background: #fff;
  border-radius: 8px;
}
.ruleText {
  font-size: 0.95em;
  color: black;
}
.danger {
  padding: 8px 10px;
  border: 1px solid #c33;
  background: #fff;
  cursor: pointer;
  color: #000;
}

/* Calendar */
.calendar {
  border: 1px solid #eee;
  border-radius: 8px;
  padding: 12px;
  background: #fff;
}
.calHeader {
  display: grid;
  grid-template-columns: 70px 1fr;
  gap: 8px;
  align-items: center;
  margin-bottom: 8px;
}
.timeColHeader {
  height: 1px;
}
.dayHeaders {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 8px;
}
.dayHeader {
  text-align: center;
  font-weight: 700;
  padding: 6px 0;
  border-bottom: 1px solid #eee;
}
.calBody {
  display: grid;
  grid-template-columns: 70px 1fr;
  gap: 8px;
}
.timeCol {
  position: relative;
}
.timeLabel {
  position: absolute;
  left: 0;
  transform: translateY(-50%);
  font-size: 0.85em;
  color: #555;
}
.daysGrid {
  display: grid;
  grid-template-columns: repeat(7, minmax(140px, 1fr));
  gap: 12px;
  overflow-x: auto;
}

.dayCol {
  position: relative;
  border: 1px solid #eee;
  border-radius: 8px;
  background: #fafafa;
  overflow: hidden;
}
.gridLine {
  position: absolute;
  left: 0;
  right: 0;
  height: 1px;
  background: #eee;
}
.ruleBlock {
  position: absolute;
  left: 6px;
  right: 6px;
  border: 1px solid #9bb7ff;
  background: #e8efff;
  border-radius: 8px;
  padding: 6px;
  box-sizing: border-box;
  color: #000;
}
.ruleTitle {
  font-weight: 700;
  font-size: 0.95em;
}
.ruleSub {
  font-size: 0.85em;
  color: #334;
}

.card h3 {
  color: black;
}

h2 {
  color: black;
}

/* Small screens */
@media (max-width: 980px) {
  .adminLayout {
    grid-template-columns: 1fr;
  }
}
</style>
