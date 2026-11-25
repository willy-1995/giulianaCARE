import React, { useCallback, useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import "../styles/admin_dashboard.scss";
import {
  loadClients,
  loadContacts,
  loadProtocol,
  loadUser,
} from "../../assets/loader";

interface UserRecord {
  id: number;
  email: string;
  role?: string;
  price?: string | null;
  country?: string | null;
  area_code?: string | null;
  subscription_status?: string | null;
  created_at?: string;
}

interface MonthlyGrowthRow {
  key: string; // z. B. "2026-11"
  year: number;
  month: number; // 1-12
  label: string; // z. B. "Nov 2026"
  count: number;
  momDiff: number | null; // Veränderung zum Vormonat (absolut)
  momRate: number | null; // Zuwachsrate zum Vormonat in %
  yoyDiff: number | null; // Vergleich zum Vorjahresmonat (absolut)
  yoyRate: number | null; // Vergleich zum Vorjahresmonat in %
}

function formatSignedNumber(val: number | null): string {
  if (val === null || Number.isNaN(val)) return "–";
  if (val > 0) return `+${val}`;
  return `${val}`;
}

function formatSignedPercent(val: number | null): string {
  if (val === null || Number.isNaN(val) || !Number.isFinite(val)) return "–";
  const rounded = Math.round(val * 10) / 10;
  if (rounded > 0) return `+${rounded}%`;
  return `${rounded}%`;
}

function calcGrowthRate(
  current: number,
  previous: number | null,
): number | null {
  if (previous === null) return null;
  if (previous === 0) {
    return current > 0 ? 100 : 0;
  }
  return ((current - previous) / previous) * 100;
}

function getTrendClassName(diff: number | null): string {
  if (diff === null || diff === 0) return "admin-dashboard__trend-cell";
  return diff > 0
    ? "admin-dashboard__trend-cell admin-dashboard__trend-cell--positive"
    : "admin-dashboard__trend-cell admin-dashboard__trend-cell--negative";
}

export default function AdminDashboard() {
  const navigate = useNavigate();

  const [users, setUsers] = useState<UserRecord[]>([]);
  const [clientsCount, setClientsCount] = useState<number>(0);
  const [contactsCount, setContactsCount] = useState<number>(0);
  const [incidentsCount, setIncidentsCount] = useState<number>(0);
  const [loadingStats, setLoadingStats] = useState<boolean>(false);
  const [statsError, setStatsError] = useState<string | null>(null);

  const handleLogout = useCallback(() => {
    localStorage.removeItem("token");
    navigate("/admin-registration", { replace: true });
  }, [navigate]);

  const fetchAllStats = useCallback(async () => {
    const token = localStorage.getItem("token");
    if (!token) {
      navigate("/admin-registration", { replace: true });
      return;
    }

    setLoadingStats(true);
    setStatsError(null);

    try {
      const [userRes, clientsRes, contactsRes, protocolRes] = await Promise.all(
        [
          loadUser(() => {}),
          loadClients(() => {}),
          loadContacts(() => {}),
          loadProtocol(() => {}),
        ],
      );

      const rawUsers: UserRecord[] = Array.isArray(userRes)
        ? userRes
        : Array.isArray(userRes?.users)
          ? userRes.users
          : Array.isArray(userRes?.data)
            ? userRes.data
            : [];

      if (userRes && userRes.success === false && rawUsers.length === 0) {
        setStatsError(
          userRes.message || "Nutzerdaten konnten nicht geladen werden.",
        );
      }
      setUsers(rawUsers);

      const rawClients = Array.isArray(clientsRes)
        ? clientsRes
        : Array.isArray(clientsRes?.clients)
          ? clientsRes.clients
          : Array.isArray(clientsRes?.data)
            ? clientsRes.data
            : [];
      setClientsCount(rawClients.length);

      const rawContacts = Array.isArray(contactsRes)
        ? contactsRes
        : Array.isArray(contactsRes?.contacts)
          ? contactsRes.contacts
          : Array.isArray(contactsRes?.data)
            ? contactsRes.data
            : [];
      setContactsCount(rawContacts.length);

      const rawIncidents = Array.isArray(protocolRes)
        ? protocolRes
        : Array.isArray(protocolRes?.incidents)
          ? protocolRes.incidents
          : Array.isArray(protocolRes?.protocol)
            ? protocolRes.protocol
            : Array.isArray(protocolRes?.data)
              ? protocolRes.data
              : [];
      setIncidentsCount(rawIncidents.length);
    } catch (err) {
      console.error("Fehler beim Laden der Dashboard-Statistiken:", err);
      setStatsError("Fehler beim Laden der Statistikdaten.");
    } finally {
      setLoadingStats(false);
    }
  }, [navigate]);

  useEffect(() => {
    fetchAllStats();
  }, [fetchAllStats]);

  const analytics = useMemo(() => {
    const monthFormatter = new Intl.DateTimeFormat("de-DE", {
      month: "short",
      year: "numeric",
    });

    const countsByMonth = new Map<
      string,
      { year: number; month: number; count: number }
    >();
    const validDates: Date[] = [];

    for (const u of users) {
      if (!u.created_at) continue;
      const normalized = u.created_at.includes("T")
        ? u.created_at
        : u.created_at.replace(" ", "T");
      const parsed = new Date(normalized);
      if (Number.isNaN(parsed.getTime())) continue;

      validDates.push(parsed);
      const year = parsed.getFullYear();
      const month = parsed.getMonth() + 1;
      const key = `${year}-${String(month).padStart(2, "0")}`;

      const current = countsByMonth.get(key) || { year, month, count: 0 };
      current.count += 1;
      countsByMonth.set(key, current);
    }

    const rows: MonthlyGrowthRow[] = [];

    if (countsByMonth.size > 0) {
      const sortedKeys = Array.from(countsByMonth.keys()).sort();
      const [startYear, startMonth] = sortedKeys[0].split("-").map(Number);

      const now = new Date();
      const [lastDataYear, lastDataMonth] = sortedKeys[sortedKeys.length - 1]
        .split("-")
        .map(Number);

      let endYear = lastDataYear;
      let endMonth = lastDataMonth;
      if (
        now.getFullYear() > endYear ||
        (now.getFullYear() === endYear && now.getMonth() + 1 > endMonth)
      ) {
        endYear = now.getFullYear();
        endMonth = now.getMonth() + 1;
      }

      let curYear = startYear;
      let curMonth = startMonth;

      while (
        curYear < endYear ||
        (curYear === endYear && curMonth <= endMonth)
      ) {
        const key = `${curYear}-${String(curMonth).padStart(2, "0")}`;
        const count = countsByMonth.get(key)?.count ?? 0;

        const prevMonthDate = new Date(curYear, curMonth - 2, 1);
        const prevKey = `${prevMonthDate.getFullYear()}-${String(
          prevMonthDate.getMonth() + 1,
        ).padStart(2, "0")}`;
        const prevRow = rows.find((r) => r.key === prevKey);
        const prevCount = prevRow ? prevRow.count : null;

        const momDiff = prevCount !== null ? count - prevCount : null;
        const momRate = calcGrowthRate(count, prevCount);

        const yoyKey = `${curYear - 1}-${String(curMonth).padStart(2, "0")}`;
        const yoyRow = rows.find((r) => r.key === yoyKey);
        const yoyCount = yoyRow ? yoyRow.count : null;

        const yoyDiff = yoyCount !== null ? count - yoyCount : null;
        const yoyRate = calcGrowthRate(count, yoyCount);

        rows.push({
          key,
          year: curYear,
          month: curMonth,
          label: monthFormatter.format(new Date(curYear, curMonth - 1, 1)),
          count,
          momDiff,
          momRate,
          yoyDiff,
          yoyRate,
        });

        curMonth += 1;
        if (curMonth > 12) {
          curMonth = 1;
          curYear += 1;
        }
      }
    }

    let strongestMonth: MonthlyGrowthRow | null = null;
    for (const r of rows) {
      if (!strongestMonth || r.count > strongestMonth.count) {
        strongestMonth = r;
      }
    }

    const targetYear =
      rows.length > 0 ? rows[rows.length - 1].year : new Date().getFullYear();
    const yearRows = rows.filter((r) => r.year === targetYear);

    const yearStartCount = yearRows.length > 0 ? yearRows[0].count : 0;
    const yearEndCount =
      yearRows.length > 0 ? yearRows[yearRows.length - 1].count : 0;
    const yearStartLabel =
      yearRows.length > 0 ? yearRows[0].label : `Jan ${targetYear}`;
    const yearEndLabel =
      yearRows.length > 0
        ? yearRows[yearRows.length - 1].label
        : `Dez ${targetYear}`;

    const yearSpanDiff = yearEndCount - yearStartCount;
    const yearSpanRate = calcGrowthRate(yearEndCount, yearStartCount);

    return {
      totalUsers: users.length,
      rows,
      strongestMonth,
      targetYear,
      yearStartLabel,
      yearEndLabel,
      yearStartCount,
      yearEndCount,
      yearSpanDiff,
      yearSpanRate,
      hasValidDates: validDates.length > 0,
    };
  }, [users]);

  return (
    <div className="admin-dashboard">
      <div className="admin-dashboard__header">
        <div>
          <h2 className="admin-dashboard__title">Statistik-Übersicht</h2>
          <p className="admin-dashboard__subtitle">
            Auswertung für {analytics.targetYear} (Jahre werden automatisch
            ergänzt)
          </p>
        </div>
        <div className="admin-dashboard__actions">
          <button
            type="button"
            className="admin-dashboard__refresh-btn"
            onClick={fetchAllStats}
            disabled={loadingStats}
          >
            {loadingStats ? "Aktualisiere..." : "Aktualisieren"}
          </button>
          <button
            type="button"
            className="admin-dashboard__logout-btn"
            onClick={handleLogout}
          >
            Ausloggen
          </button>
        </div>
      </div>

      {statsError && <div className="admin-dashboard__error">{statsError}</div>}

      {/* KPI-Kacheln auf einen Blick */}
      <div className="admin-dashboard__kpis">
        <div className="admin-dashboard__kpi-card">
          <div className="admin-dashboard__kpi-label">
            1. Anzahl aller Kunden (User)
          </div>
          <div className="admin-dashboard__kpi-value">
            {loadingStats ? "…" : analytics.totalUsers}
          </div>
          <div className="admin-dashboard__kpi-meta">
            Clients: {clientsCount} · Kontakte: {contactsCount} · Vorfälle:{" "}
            {incidentsCount}
          </div>
        </div>

        <div className="admin-dashboard__kpi-card">
          <div className="admin-dashboard__kpi-label">
            Jahresende zu Jahresanfang ({analytics.targetYear})
          </div>
          <div className="admin-dashboard__kpi-value admin-dashboard__kpi-value--compact">
            {formatSignedNumber(analytics.yearSpanDiff)}{" "}
            <span className="admin-dashboard__kpi-change-rate">
              ({formatSignedPercent(analytics.yearSpanRate)})
            </span>
          </div>
          <div className="admin-dashboard__kpi-meta">
            {analytics.yearStartLabel}: {analytics.yearStartCount} →{" "}
            {analytics.yearEndLabel}: {analytics.yearEndCount}
          </div>
        </div>

        <div className="admin-dashboard__kpi-card">
          <div className="admin-dashboard__kpi-label">
            Stärkster Zuwachs-Monat
          </div>
          <div className="admin-dashboard__kpi-value admin-dashboard__kpi-value--compact">
            {analytics.strongestMonth
              ? `${analytics.strongestMonth.label} (${analytics.strongestMonth.count})`
              : "–"}
          </div>
          <div className="admin-dashboard__kpi-meta">
            {analytics.strongestMonth
              ? `Vormonat: ${formatSignedPercent(analytics.strongestMonth.momRate)}`
              : "Noch keine Monatsdaten"}
          </div>
        </div>
      </div>

      {/* Monatliche Neu-Registrierungen & Zuwachsraten */}
      <div className="admin-dashboard__table-card">
        <h3 className="admin-dashboard__table-title">
          2. Neu-Registrierungen je Monat & Zuwachsraten
        </h3>

        {!analytics.hasValidDates && users.length > 0 && (
          <p className="admin-dashboard__warning">
            Hinweis: In den geladenen User-Daten fehlt aktuell das Feld{" "}
            <code>created_at</code>. Stelle sicher, dass{" "}
            <code>users_manager.php</code> das Feld <code>created_at</code> mit
            zurückgibt.
          </p>
        )}

        <table className="admin-dashboard__table">
          <thead>
            <tr>
              <th>Monat (Jahr)</th>
              <th>Neu-Registrierungen</th>
              <th>Zum Vormonat (abs. / %)</th>
              <th>Zum Vorjahresmonat (abs. / %)</th>
              <th>Markierung</th>
            </tr>
          </thead>
          <tbody>
            {analytics.rows.length === 0 ? (
              <tr>
                <td colSpan={5} className="admin-dashboard__empty-cell">
                  Keine Registrierungsdaten vorhanden.
                </td>
              </tr>
            ) : (
              analytics.rows.map((row) => {
                const isStrongest =
                  analytics.strongestMonth?.key === row.key && row.count > 0;

                return (
                  <tr
                    key={row.key}
                    className={isStrongest ? "is-strongest" : undefined}
                  >
                    <td className="admin-dashboard__month-cell">{row.label}</td>
                    <td>{row.count}</td>
                    <td className={getTrendClassName(row.momDiff)}>
                      {formatSignedNumber(row.momDiff)} (
                      {formatSignedPercent(row.momRate)})
                    </td>
                    <td className={getTrendClassName(row.yoyDiff)}>
                      {formatSignedNumber(row.yoyDiff)} (
                      {formatSignedPercent(row.yoyRate)})
                    </td>
                    <td>{isStrongest ? "Stärkster Zuwachs" : "–"}</td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
