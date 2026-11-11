import { useState, useEffect } from "react";
import { BrowserRouter as Router, Routes, Route } from "react-router-dom";
import Landing from "./routes/landing";
import TeleCare from "./routes/telecare";
import Registration from "./routes/registration";
import Login from "./routes/login";
import { ProtectedRoute } from "./routes/protectedRoutes";
import Dashboard from "./routes/dashboard";
import Settings from "./routes/settings";
import Request from "./routes/request";
import TermsAndConditions from "./routes/termsAndConditions";
import Feedback from "./routes/feedback";
import { Datasecurity } from "./routes/datasecurity";
import { Imprint } from "./routes/imprint";
import { Salespartnership } from "./routes/salespartnership";
import ResetPassword from "./routes/resetPassword";
import Metrics from "./routes/metrics";
import AdminLogin from "./routes/admin_login";
import AdminRegistration from "./routes/admin_registration";
import "./routes/styles/main.scss";
import { ScrollToTop } from "./routes/toTop";

// ==========================================
// CAREMATCH KOMPONENTEN
// ==========================================
import CareMatchLanding from "./routes/carematch";
import CarematchRegistration from "./routes/carematch_registration";
import CarematchLogin from "./routes/carematch_login";
import CarematchDashboard from "./routes/carematch_dashboard";
import CarematchProfileView from "./routes/carematch_profileView";
import CarematchOwnProfile from "./routes/carematch_ownprofile";
import CarematchEditProfile from "./routes/carematch_editprofile";

function App() {
  const [token, setToken] = useState<string | null>(
    localStorage.getItem("token"),
  );
  const [myId, setMyId] = useState<number | null>(
    localStorage.getItem("myId") ? Number(localStorage.getItem("myId")) : null,
  );

  useEffect(() => {
    document.title = "giulianaCARE";
  }, []);

  return (
    <div>
      <Router>
        <ScrollToTop />
        <Routes>
          {/* Bestehende öffentliche Routen */}
          <Route path="/" element={<TeleCare />} />
          <Route path="/landing" element={<Landing />} />
          <Route path="/telecare" element={<TeleCare />} />
          <Route path="/registration" element={<Registration />} />
          <Route path="/login" element={<Login />} />
          <Route path="/resetPassword" element={<ResetPassword />} />
          <Route path="/request" element={<Request />} />
          <Route path="/termsAndConditions" element={<TermsAndConditions />} />
          <Route path="/imprint" element={<Imprint />} />
          <Route path="/datasecurity" element={<Datasecurity />} />
          <Route path="/feedback" element={<Feedback />} />
          <Route path="/salespartnership" element={<Salespartnership />} />
          <Route path="/admin_login" element={<AdminLogin />} />
          <Route path="/admin_registration" element={<AdminRegistration />} />

          {/* ========================================== */}
          {/* CAREMATCH ROUTEN                            */}
          {/* ========================================== */}

          <Route path="/carematch_login" element={<CarematchLogin />} />
          <Route
            path="/carematch_registration"
            element={<CarematchRegistration />}
          />
          <Route path="/carematch" element={<CareMatchLanding />} />

          <Route
            path="/dashboard"
            element={
              <ProtectedRoute>
                <CarematchDashboard />
              </ProtectedRoute>
            }
          />
          <Route
            path="/carematch_profile/:id"
            element={
              <ProtectedRoute>
                <CarematchProfileView />
              </ProtectedRoute>
            }
          />
          <Route
            path="/carematch_profile"
            element={
              <ProtectedRoute>
                <CarematchOwnProfile />
              </ProtectedRoute>
            }
          />
          <Route
            path="/carematch_profile/edit"
            element={
              <ProtectedRoute>
                <CarematchEditProfile />
              </ProtectedRoute>
            }
          />

          {/* ========================================== */}
          {/* TELECARE ROUTEN                         */}
          {/* ========================================== */}
          {/* Bestehende geschützte Routen */}
          <Route
            path="/dashboard"
            element={
              <ProtectedRoute>
                <Dashboard />
              </ProtectedRoute>
            }
          />
          <Route
            path="/settings"
            element={
              <ProtectedRoute>
                <Settings />
              </ProtectedRoute>
            }
          />
          <Route
            path="/metrics"
            element={
              <ProtectedRoute>
                <Metrics />
              </ProtectedRoute>
            }
          />
        </Routes>
      </Router>
    </div>
  );
}

export default App;
