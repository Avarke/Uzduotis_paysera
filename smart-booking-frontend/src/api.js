const BASE = import.meta.env.VITE_API_BASE_URL;

async function request(path, options = {}) {
  const res = await fetch(`${BASE}${path}`, {
    headers: {
      "Content-Type": "application/json",
      "Accept": "application/json",
    },
    ...options,
  });

  const text = await res.text();
  const data = text ? JSON.parse(text) : null;

  if (!res.ok) {
    throw new Error(data?.message || `HTTP ${res.status}`);
  }
  return data;
}

export const api = {

  //client
  getServices: () => request("/services"),
  getAvailability: (date) => request(`/availability?date=${encodeURIComponent(date)}`),
  createBooking: (payload) =>
    request("/client-bookings", { method: "POST", body: JSON.stringify(payload) }),

  // coach, admin
  getWorkRules: () => request("/work-rules"),
    createWorkRule: (payload) =>
      request("/work-rules", { method: "POST", body: JSON.stringify(payload) }),
    deleteWorkRule: (id) =>
      request(`/work-rules/${id}`, { method: "DELETE" }),
  
};
