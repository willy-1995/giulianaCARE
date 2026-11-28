import React, { useState, useEffect, useRef } from "react";
import { Link } from "react-router-dom";
import { useNavigate } from "react-router-dom";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons";
import {
  faTimes,
  faCheck,
  faPhone,
  faEnvelope,
  faXmark,
  faThumbsUp,
  faUsers,
  faPhoneVolume,
  faTriangleExclamation,
  faHandHoldingHeart,
  faShieldHalved,
  faRobot,
  faCircleQuestion,
  faHandshakeAngle,
  faHeart,
} from "@fortawesome/free-solid-svg-icons";
import Navbar from "./components/navbar";
import Footer from "./components/footer";
import TestCallForm from "../assets/testCallForm";
import "./styles/main.scss";
import "./styles/repair.scss";
import { loadClients } from "../assets/loader";
import { loadVapiStats } from "../assets/loader";
import { price1, price2, price3, corpName } from "../assets/constants";

function RepairSite() {
  const [isOpen, setIsOpen] = useState(false);
  const [mobileMenue, setMobileMenue] = useState(false);
  const [currentSlide, setCurrentSlide] = useState(0);
  const [modal, setModal] = useState(false);

  // State & Bilder-Array für die Mobile-Image-Slideshow
  const [currentImageIndex, setCurrentImageIndex] = useState(0);

  const getAssetUrl = (path: string) => `${import.meta.env.BASE_URL}${path}`;

  const mobileImages = [
    getAssetUrl("media/grandma_tel.png"),
    getAssetUrl("media/team.png"),
    getAssetUrl("media/woman.jpg"),
  ];

  // 1. Zielwerte aus der API
  const [targetCount, setTargetCount] = useState<number>(14);
  const [targetMinutes, setTargetMinutes] = useState<number>(0);

  // 2. Die animierten Werte für die Anzeige
  const [displayCount, setDisplayCount] = useState<number>(0);
  const [displayMinutes, setDisplayMinutes] = useState<number>(0);

  // Ref für das HTML-Element (zum Erkennen des Scrollens)
  const statsRef = useRef<HTMLDivElement>(null);

  // Ref um sicherzustellen, dass die Animation nur einmal abläuft
  const hasAnimated = useRef<boolean>(false);

  // SLIDESHOW TEXT
  const slidesData = [
    "Tägliche Betreuungsanrufe, Medikamenten- und Terminerinnerungen. 365 Tage",
    "Keine Extra-Geräte. Keine Installation. Die betreute Person muss nur den Hörer abnehmen!",
    "Sorgen Sie präventiv für die Eigenständigkeit ihres Senioren",
    "Hohe Datenschutzvorkehrungen und Serverstandort in Deutschland",
  ];

  const navigate = useNavigate();

  // TIMER SLIDESHOW PHRASES
  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentSlide((prevSlide) => (prevSlide + 1) % slidesData.length);
    }, 10000);

    return () => clearInterval(timer);
  }, [slidesData.length]);

  // TIMER SLIDESHOW IMAGES MOBILE
  useEffect(() => {
    const imageTimer = setInterval(() => {
      setCurrentImageIndex(
        (prevIndex) => (prevIndex + 1) % mobileImages.length,
      );
    }, 6000);

    return () => clearInterval(imageTimer);
  }, [mobileImages.length]);

  //LOAD STATS
  // A) Daten aus der API laden (Klienten-Anzahl & Vapi-Minuten)
  useEffect(() => {
    const fetchStatsData = async () => {
      // 1. Klienten laden
      const clientResult = await loadClients(() => {});
      if (
        clientResult &&
        clientResult.success &&
        Array.isArray(clientResult.data)
      ) {
        const realCount = clientResult.data.length;
        setTargetCount(realCount >= 15 ? realCount : 14);
      }

      // 2. Vapi-Minuten laden
      const vapiResult = await loadVapiStats();
      if (vapiResult && vapiResult.success) {
        setTargetMinutes(vapiResult.totalMinutes || 0);
      }
    };

    fetchStatsData();
  }, []);

  // B) Scroll-Erkennung & synchrone Hochzähl-Animation
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        const [entry] = entries;

        if (entry.isIntersecting && !hasAnimated.current) {
          hasAnimated.current = true;

          const duration = 1500; // Dauer der Animation in ms (1.5 Sekunden)
          const steps = 50; // Anzahl der Zwischenschritte für flüssige Animation
          const stepTime = duration / steps;
          let currentStep = 0;

          const timer = setInterval(() => {
            currentStep++;
            const progress = currentStep / steps;

            // Werte schrittweise berechnen
            setDisplayCount(Math.round(targetCount * progress));
            setDisplayMinutes(Math.round(targetMinutes * progress));

            if (currentStep >= steps) {
              setDisplayCount(targetCount);
              setDisplayMinutes(targetMinutes);
              clearInterval(timer);
            }
          }, stepTime);
        }
      },
      { threshold: 0.3 },
    );

    if (statsRef.current) {
      observer.observe(statsRef.current);
    }

    return () => observer.disconnect();
  }, [targetCount, targetMinutes]);

  // FUNCTIONS
  const openModal = () => {
    setModal(true);
  };

  const closeModal = () => {
    setModal(false);
  };

  const toRegist = () => {
    navigate("/registration");
  };

  return (
    <div className="body-div repair-content">
      <main className="maintenance-container">
        <div className="maintenance-content">
          <img
            src={getAssetUrl("media/giulianaCareCut.png")}
            alt="logo"
            id="logo"
          />

          <h1>Wartungsarbeiten</h1>

          <p>
            giulianaCARE ist schnellstmöglich wieder erreichbar.
            <br />
            Alle aktuellen Anrufe werden weiterhin durchgeführt!
          </p>
          <div className="contact-div">
            <h2>Kontakt</h2>

            <a
              href="https://wa.me/4915170135563"
              target="_blank"
              rel="noopener noreferrer"
            >
              <h3>
                <FontAwesomeIcon icon={faWhatsapp} /> Whatsapp
              </h3>
            </a>
            <Link to={"/request"}>
              <h3>
                <FontAwesomeIcon icon={faEnvelope} /> E-Mail
              </h3>
            </Link>
            {/*
          <Link to={"/salespartnership"}>
            <h3>
              <FontAwesomeIcon icon={faHandshake} /> Vertriebspartner werden
            </h3>
          </Link>
          */}
          </div>
        </div>
      </main>

      <Footer />
    </div>
  );
}

export default RepairSite;
