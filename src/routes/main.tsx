import React from "react";
import { useState, useEffect } from "react";
import { useLocation } from "react-router-dom";
import { useNavigate } from "react-router-dom";
import { Navbar } from "../assets/navbar";
import "./style_main.css";
import { Link } from "react-router-dom";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { faSquareXmark } from "@fortawesome/free-solid-svg-icons";
import { faQuestion } from "@fortawesome/free-solid-svg-icons";
import { faCaretUp } from "@fortawesome/free-solid-svg-icons";
import { faMessage } from "@fortawesome/free-solid-svg-icons";
import { faHeart } from "@fortawesome/free-solid-svg-icons";
import { faFaceGrinHearts } from "@fortawesome/free-solid-svg-icons";
import { faFilter } from "@fortawesome/free-solid-svg-icons";
import { faXmark } from "@fortawesome/free-solid-svg-icons";
import { faPaperPlane } from "@fortawesome/free-solid-svg-icons";
import { faMagnifyingGlass } from "@fortawesome/free-solid-svg-icons";
import { loadPictures } from "../assets/showPictures";
import { text } from "@fortawesome/fontawesome-svg-core";
import { useLikesAndProfiles } from "../assets/hooks/useLikesandProfiles";

interface User {
  id: number;
  name: string;
  city_name: string;
  country_name: string;
  state_name: string;
  age: number | null;
  birthday: string;
  gender: string;
  sex: string;
  height: number;
  ethnicity: string;
  degree: string;
  work: string | null;
  profiletext: string;
  seachtext: string;
  hub: string;
  profile_pic: string | null;
  all_pics?: { filename: string; is_profile: number }[];
  message_privacy: number;
}

export default function Main() {
  //STATES

  const [selectedUser, setSelectedUser] = useState<User | null>(null);

  const {
    usersList,
    likedUserIds,
    setLikedUserIds,
    matchedUserIds,
    activeChatUserIds,
    fansList,
    whoLikedMeIds,
    loading,
    setLoading,
    error,
    refresh, // Hilfreich, falls du nach einem Like neu laden willst
  } = useLikesAndProfiles();

  const [activeFilters, setActiveFilters] = useState({
    gender: [] as string[],
    sex: [] as string[],
    ethnicity: [] as string[],
    country_name: [] as string[],
    state_name: [] as string[],
    city_name: [] as string[],
    degree: [] as string[],
    onlyLiked: false,
    // premium: false, // Sonderfall: Boolean
  });
  const [fullscreenImage, setFullscreenImage] = useState<string | null>(null);
  const [filterModal, setFilterModal] = useState(false);
  const [swipeModal, setSwipeModal] = useState(true);
  const [currentIndex, setCurrentIndex] = useState(0); //index for swiping
  //FOR MESSAGING
  const [messageModal, setMessageModal] = useState(false);
  const [initMessage, setInitMessage] = useState("");
  const [isSending, setIsSending] = useState(false);
  const [aiHelp, setAiHelp] = useState(false);
  const [aiHelpStyle, setAiHelpStyle] = useState("funny");
  const [jerry, setJerry] = useState("Ich helfe Dir gerne beim Einstieg!");
  const [likeAnimation, setLikeAnimation] = useState(false);
  const [selectView, setSelectView] = useState(true);

  const navigate = useNavigate();

  //========================
  //FUNCTIONS
  //=======================

  //=========
  //FILTER
  //=========
  const toggleFilter = (category: string, value: string) => {
    setActiveFilters((prev) => {
      const currentCategoryValues = prev[
        category as keyof typeof prev
      ] as string[];

      const newValues = currentCategoryValues.includes(value)
        ? currentCategoryValues.filter((v) => v !== value) // Entfernen, wenn schon drin
        : [...currentCategoryValues, value]; // Hinzufügen, wenn nicht drin

      return { ...prev, [category]: newValues };
    });
  };

  //FILTERED USERS (before useLocation from other routes bc of variable declaration order (filteredUsers is used in later block))
  const filteredUsers = usersList.filter((user) => {
    // 1. NEU: Prüfen, ob der User bereits gelikt wurde
    const isAlreadyLiked = likedUserIds.has(user.id); //has bc its set not array
    const isMatch = matchedUserIds.has(user.id);
    const hasActiveChat = activeChatUserIds.has(user.id);

    //only likes!
    if (activeFilters.onlyLiked) {
      if (!isAlreadyLiked || isMatch || hasActiveChat) return false;
    } else {
      if (isAlreadyLiked || isMatch || hasActiveChat) return false;
    }

    const matchesGender =
      activeFilters.gender.length === 0 ||
      activeFilters.gender.includes(user.gender);
    const matchesSex =
      activeFilters.sex.length === 0 || activeFilters.sex.includes(user.sex);
    const matchesEthnicity =
      activeFilters.ethnicity.length === 0 ||
      activeFilters.ethnicity.includes(user.ethnicity);
    const matchesDegree =
      activeFilters.degree.length === 0 ||
      activeFilters.degree.includes(user.degree);

    return matchesGender && matchesEthnicity && matchesSex;
  });

  //LOAD PROFILES

  //LOAD PICTURES
  useEffect(() => {
    loadPictures();
  }, []);
  console.log("Bilder geladen");

  //COME FROM LIKES LIST (CHAT.TSX)
  const location = useLocation();

  useEffect(() => {
    const fanFromState = location.state?.openProfile as User | null;

    // Suche im FILTERED Array, damit der Index auch wirklich zum Swiper passt
    if (fanFromState && filteredUsers.length > 0) {
      const index = filteredUsers.findIndex((u) => u.id === fanFromState.id);

      if (index !== -1) {
        setCurrentIndex(index);
        setSwipeModal(true);
        window.history.replaceState({}, document.title);
      }
    }
  }, [location.state, filteredUsers]); // Achte auf filteredUsers als Dependency

  // Verhindert das Scrollen des Hintergrunds, wenn das Swiper-Modal offen ist
  useEffect(() => {
    if (swipeModal) {
      document.body.style.overflow = "hidden";
    } else {
      document.body.style.overflow = "unset";
    }

    // Cleanup-Funktion (falls die Komponente unmounted)
    return () => {
      document.body.style.overflow = "unset";
    };
  }, [swipeModal]);
  //===============================
  //MODAL - PROFILE VIEW
  //===============================
  //SHOW SELECTED PROFILE
  const openProfile = (user: User) => {
    // Finde den Index des geklickten Users in der gefilterten Liste
    const userIndex = filteredUsers.findIndex((u) => u.id === user.id);

    if (userIndex !== -1) {
      setCurrentIndex(userIndex);
      setSelectedUser(user); // Behalten wir für Logik-Referenzen
      setSwipeModal(true);
    }
  };

  //CLOSE PROFILE
  const closeProfile = () => {
    setSelectedUser(null);
  };

  //OPEN FILTER MODAL
  const openFilterModal = () => {
    setFilterModal(true);
  };

  //CLOSE FILTER MODAL
  const closeFilterModal = () => {
    setFilterModal(false);
  };

  //===============
  //SWIPING
  //===============
  const openSwiperModal = () => {
    setSwipeModal(true);
  };

  const nextProfile = () => {
    if (currentIndex < filteredUsers.length - 1) {
      setCurrentIndex((prev) => prev + 1);
    } else {
      alert("Das waren aktuell alle Profile!");
    }
  };

  const prevProfile = () => {
    if (currentIndex > 0) {
      setCurrentIndex((prev) => prev - 1);
    }
  };

  //=========
  //INIT MESSAGE
  //=========

  const startChat = async () => {
    console.log("STARTCHAT geklickt!"); //DEBUG
    if (!initMessage.trim() || !selectedUser) return;
    setIsSending(true);
    console.log("isSending true"); //DEBUG

    const token = localStorage.getItem("token");
    console.log("Sende Nachricht mit Token:", token);

    try {
      const response = await fetch(
        "http://localhost/datingapp_ki/api/conversation.php",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify({
            partner_id: selectedUser.id,
            message_text: initMessage,
          }),
        },
      );

      const result = await response.json();

      if (result.success) {
        alert("Nachricht gesendet!");
        setMessageModal(false);
        setInitMessage("");
        // Optional: Leite den User direkt zum Messenger weiter
        // window.location.href = "/messenger";
      } else {
        alert("Fehler: " + result.message);
      }
    } catch (err) {
      console.error(err);
      alert("Netzwerkfehler");
    } finally {
      setIsSending(false);
    }
  };

  //===============
  //DIREKT MESSAGE
  //===============

  const handleMessageClick = async () => {
    // Hol dir den aktuellen User aus dem Swiper-Index
    const currentUser = filteredUsers[currentIndex];
    if (!currentUser) return;

    // WICHTIG: Setze den selectedUser hier!
    setSelectedUser(currentUser);

    const token = localStorage.getItem("token");

    try {
      // Wir fragen den conversation_manager, ob eine Conv existiert
      // Wir nutzen POST, weil wir dem Server sagen: "Suche oder erstelle (aber ohne Nachricht)"
      const response = await fetch(
        "http://localhost/datingapp_ki/api/conversation.php",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify({
            partner_id: currentUser.id,
            check_only: true, // Ein Flag für das Backend
          }),
        },
      );

      const result = await response.json();

      if (result.success) {
        if (result.exists) {
          // FALL A: Chat existiert bereits -> Direkt zum Messenger
          // Wir übergeben die ID, damit der Messenger weiß, welchen Chat er öffnen soll
          navigate("/chats", {
            state: { openChatId: result.conversation_id },
          });
        } else {
          // FALL B: Kein Chat da -> Modal für erste Nachricht öffnen
          setMessageModal(true);
        }
      }
    } catch (err) {
      console.error("Fehler beim Chat-Check:", err);
    }
  };

  //==============
  //AI HELP MODAL
  //==============
  const showAiHelp = () => {
    setAiHelp(true);
  };

  //===============
  //AI MESSAGE HELP
  //===============
  const aiHelpMessage = async () => {
    if (!selectedUser) return;

    const token = localStorage.getItem("token");

    setLoading(true);

    setJerry("Lass mich nachdenken...");

    // Vorbereitung der Daten
    const profileData = {
      target_id: selectedUser.id,
      hub: selectedUser.hub,
      profiletext: selectedUser.profiletext,
      style: aiHelpStyle,
    };

    try {
      const response = await fetch(
        "http://localhost/datingapp_ki/api/ai_help.php",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`, // Falls dein Backend den User verifizieren muss
          },
          body: JSON.stringify(profileData),
        },
      );

      const result = await response.json();

      if (result.status === "success") {
        setJerry(
          "Wie findest du meinen Vorschlag? Oder soll ich weitergrübeln?",
        );
        console.log("Erfolgreich an PHP gesendet:", result.answere); //DEBUG
        setInitMessage(result.answere);
      } else {
        console.error("Fehler vom PHP-Skript:", result.answere);
      }
    } catch (err) {
      console.error("Verbindungsfehler", err);
    } finally {
      setLoading(false);
    }
  };

  //LIKE ANIMATION
  const triggerLikeAnimation = () => {
    console.log("like geklickt");
    setLikeAnimation(true);
    console.log("Animation gestartet");
    // Nach 800ms (Dauer der Animation) setzen wir den State zurück
    setTimeout(() => {
      setLikeAnimation(false);
    }, 800);
  };

  //LIKE
  const handleLike = async (targetUserId: number) => {
    const token = localStorage.getItem("token");

    try {
      const response = await fetch(
        "http://localhost/datingapp_ki/api/like_manager.php",
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify({ target_id: targetUserId }),
        },
      );

      const data = await response.json();
      console.log("Serverantwort: ", data); //DEBUG

      if (data.success) {
        setLikedUserIds((prev) => {
          const newSet = new Set(prev);
          newSet.add(targetUserId);
          return newSet;
        });
        if (data.is_match) {
          // FALL A: Es ist ein Match!
          alert("It's a Match! 🎉 Ihr könnt euch jetzt schreiben.");
          // Hier könntest du den User direkt zum Chat weiterleiten
        } else {
          // FALL B: Like wurde gespeichert, aber Gegen-Like fehlt noch
          console.log("Like gesendet");
        }
      }
    } catch (error) {
      console.error("Fehler beim Liken:", error);
    }
  };

  return (
    <div className="body-div">
      <Navbar />
      <div>
        <div className="body-sub-div main-content">
          <div className="search-display-div">
            <div className="filter-div">
              <div className="filter-category">
                <strong>Ansicht:</strong>
                <label>
                  <input
                    type="checkbox"
                    checked={activeFilters.onlyLiked}
                    onChange={(e) =>
                      setActiveFilters((prev) => ({
                        ...prev,
                        onlyLiked: e.target.checked,
                      }))
                    }
                  />
                  gelikte Profile
                </label>
              </div>
              <div className="filter-category">
                <strong>Geschlecht:</strong>
                {["female", "male", "other"].map((e) => (
                  <label key={e}>
                    <input
                      type="checkbox"
                      checked={activeFilters.gender.includes(e)}
                      onChange={() => toggleFilter("gender", e)}
                    />
                    {e}
                  </label>
                ))}
              </div>

              <div className="filter-category">
                <strong>Sexualität:</strong>
                {["hetero", "homo", "bi", "other"].map((e) => (
                  <label key={e}>
                    <input
                      type="checkbox"
                      checked={activeFilters.sex.includes(e)}
                      onChange={() => toggleFilter("sex", e)}
                    />
                    {e}
                  </label>
                ))}
              </div>
              <div className="filter-category">
                <strong>Ethnie:</strong>
                {["european", "asian", "african", "latinamerican"].map((e) => (
                  <label key={e}>
                    <input
                      type="checkbox"
                      checked={activeFilters.ethnicity.includes(e)}
                      onChange={() => toggleFilter("ethnicity", e)}
                    />
                    {e}
                  </label>
                ))}
              </div>
              <div className="filter-category">
                <strong>Bildungsgrad:</strong>
                {[
                  "main-degree",
                  "real-degree",
                  "matura",
                  "bachelor",
                  "master",
                  "promotion",
                ].map((e) => (
                  <label key={e}>
                    <input
                      type="checkbox"
                      checked={activeFilters.degree.includes(e)}
                      onChange={() => toggleFilter("degree", e)}
                    />
                    {e}
                  </label>
                ))}
              </div>
            </div>
            <div className="mobile-button-div">
              <button
                className="filter-button-mobile"
                onClick={openFilterModal}
              >
                <FontAwesomeIcon icon={faFilter} className="icon" />
              </button>
            </div>
            {/**SPINNER!!! */}
            {loading ? (
              <p>Lade Profile...</p>
            ) : (
              <div className="profilesList-div">
                {filteredUsers.length > 0
                  ? filteredUsers.map((user) => (
                      <div
                        key={user.id}
                        className={`user-card ${likedUserIds.has(user.id) ? "liked" : ""}`}
                        onClick={() => openProfile(user)}
                      >
                        {likedUserIds.has(user.id) && (
                          <FontAwesomeIcon
                            icon={faHeart}
                            className="card-liked-icon"
                          />
                        )}
                        <div className="card-pic">
                          {user.profile_pic ? (
                            <img
                              src={`http://localhost/datingapp_ki/src/user_uploads/images/${user.profile_pic}`}
                              alt={user.name}
                            />
                          ) : (
                            <div className="placeholder-pic">
                              <FontAwesomeIcon icon={faQuestion} />
                            </div>
                          )}
                        </div>
                        <div className="card-info">
                          <p>
                            {user.name}, {user.age} <br /> {user.city_name}
                          </p>
                        </div>
                      </div>
                    ))
                  : !error && <p className="info-p">Keine Nutzer gefunden.</p>}
              </div>
            )}
          </div>
        </div>

        {/**=========================
         * FULLSCREEN PICTURE VIEW
         * =========================*/}
        {fullscreenImage && (
          <div
            className="fullscreen-modal"
            onClick={() => setFullscreenImage(null)}
          >
            <div className="fullscreen-content">
              <FontAwesomeIcon
                icon={faSquareXmark}
                className="close-fullscreen"
                onClick={() => setFullscreenImage(null)}
              />
              <img src={fullscreenImage} alt="Vollbildansicht" />
            </div>
          </div>
        )}
        {/**FILTERMODAL outsource!! */}
        {filterModal && (
          <div className="filterModal modal">
            <h2>Suchfilter</h2>
            <div className="mobile-filter-div">
              <div className="mobile-filter-category">
                <strong>Ansicht:</strong>
                <label>
                  <input
                    type="checkbox"
                    checked={activeFilters.onlyLiked}
                    onChange={(e) =>
                      setActiveFilters((prev) => ({
                        ...prev,
                        onlyLiked: e.target.checked,
                      }))
                    }
                  />
                  Nur gelikte Profile anzeigen
                </label>
              </div>
              <div className="mobile-filter-category">
                <strong>Geschlecht:</strong>
                {["female", "male", "other"].map((e) => (
                  <label key={e}>
                    <input
                      type="checkbox"
                      checked={activeFilters.gender.includes(e)}
                      onChange={() => toggleFilter("gender", e)}
                    />
                    {e}
                  </label>
                ))}
              </div>

              <div className="mobile-filter-category">
                <strong>Sexualität:</strong>
                {["hetero", "homo", "bi", "other"].map((e) => (
                  <label key={e}>
                    <input
                      type="checkbox"
                      checked={activeFilters.sex.includes(e)}
                      onChange={() => toggleFilter("sex", e)}
                    />
                    {e}
                  </label>
                ))}
              </div>
              <div className="mobile-filter-category">
                <strong>Ethnie:</strong>
                {["european", "asian", "african", "latinamerican"].map((e) => (
                  <label key={e}>
                    <input
                      type="checkbox"
                      checked={activeFilters.ethnicity.includes(e)}
                      onChange={() => toggleFilter("ethnicity", e)}
                    />
                    {e}
                  </label>
                ))}
              </div>
              <div className="mobile-filter-category">
                <strong>Bildungsgrad:</strong>
                {[
                  "main-degree",
                  "real-degree",
                  "matura",
                  "bachelor",
                  "master",
                  "promotion",
                ].map((e) => (
                  <label key={e}>
                    <input
                      type="checkbox"
                      checked={activeFilters.degree.includes(e)}
                      onChange={() => toggleFilter("degree", e)}
                    />
                    {e}
                  </label>
                ))}
              </div>
            </div>
            <button onClick={closeFilterModal}>Anwenden</button>
          </div>
        )}

        {/*=============
        SWIPER
        ==============*/}
        {swipeModal && filteredUsers.length > 0 && (
          <div className="modal swipeModal">
            <button
              className="detail-search-button icon-button"
              onClick={() => setSwipeModal(false)}
            >
              <FontAwesomeIcon icon={faMagnifyingGlass} className="icon" />
            </button>
            <div className="swipe-card">
              {(() => {
                const user = filteredUsers[currentIndex];
                // --- Wenn noch nicht geladen---
                if (!user) {
                  return (
                    <div className="swipe-card-scroll-content">
                      <p>Profil wird geladen...</p>
                    </div>
                  );
                }
                //Is Fan?
                const isFan = whoLikedMeIds.has(user.id);
                // ------------------------
                return (
                  <div className="swipe-card-scroll-content">
                    {/* 1. Große Bildergalerie wie im Profil-Modal */}
                    <div className="swipe-profile-header">
                      <div className="swipe-card-name">
                        <h2>
                          {user.name}, {user.age}
                        </h2>
                        <p>
                          {user.city_name}, {user.country_name}
                        </p>
                        <p>Mein Hub: {user.hub}</p>
                        {isFan && (
                          <div className="swiper-like-info">
                            <p>
                              <FontAwesomeIcon icon={faFaceGrinHearts} />
                              Hey, {user.name} mag Dich bereits!
                              <FontAwesomeIcon icon={faFaceGrinHearts} />
                            </p>
                          </div>
                        )}
                      </div>
                      <div className="main-swipe-pic-div">
                        <img
                          src={`http://localhost/datingapp_ki/src/user_uploads/images/${user.profile_pic}`}
                          alt="Leider noch kein Bild vorhanden..."
                        />
                      </div>
                    </div>

                    {/* 2. Alle Details (Scrollbarer Bereich) */}
                    <div className="swipe-detail-body">
                      <div className="swipe-profile-text-div">
                        {user.profiletext}
                      </div>

                      <div className="swipe-info-div">
                        <div className="swipe-info-item">
                          <p>Geschlecht</p> {user.gender}
                        </div>
                        <div className="swipe-info-item">
                          <p>Sexualität</p> {user.sex}
                        </div>
                        <div className="swipe-info-item">
                          <p>Größe</p> {user.height}cm
                        </div>
                        <div className="swipe-info-item">
                          <p>Ethnie</p> {user.ethnicity}
                        </div>
                        <div className="swipe-info-item">
                          <p>Abschluss</p> {user.degree}
                        </div>
                      </div>

                      {/* Galerie-Vorschau innerhalb des Swipers */}
                      {/* Galerie-Vorschau innerhalb des Swipers – ohne das Profilbild zu wiederholen */}
                      <div className="swipe-profile-gallery-div">
                        {user.all_pics
                          ?.filter(
                            (pic: { filename: string }) =>
                              pic.filename !== user.profile_pic,
                          ) // Filtere das Hauptbild heraus
                          .map((pic: { filename: string }, i: number) => (
                            <div className="swipe-gallery-item" key={i}>
                              <img
                                src={`http://localhost/datingapp_ki/src/user_uploads/images/${pic.filename}`}
                                alt={`Galeriebild ${i}`}
                                onClick={() =>
                                  setFullscreenImage(
                                    `http://localhost/datingapp_ki/src/user_uploads/images/${pic.filename}`,
                                  )
                                }
                              />
                            </div>
                          ))}
                      </div>
                    </div>

                    <div className="swipe-controls">
                      {/* ZURÜCK BUTTON */}
                      <button
                        onClick={prevProfile}
                        disabled={currentIndex === 0}
                        className="swipe-btn"
                      >
                        ‹
                      </button>

                      {/* LIKE BUTTON MIT ANIMATION */}
                      <button
                        className="swipe-btn like"
                        onClick={() => {
                          console.log("Like-Button physikalisch geklickt!"); // Test 1
                          const currentUser = filteredUsers[currentIndex];

                          if (currentUser) {
                            console.log("Liken von User:", currentUser.name); // Test 2
                            triggerLikeAnimation(); // Startet Animation + Console Log dort
                            handleLike(currentUser.id); // API Call

                            if (
                              currentIndex >= filteredUsers.length - 1 &&
                              currentIndex > 0
                            ) {
                              setCurrentIndex((prev) => prev - 1);
                            }
                          }
                        }}
                      >
                        <FontAwesomeIcon icon={faHeart} />
                      </button>
                      {/* Nur anzeigen, wenn message_privacy auf 0 (offen) steht */}
                      {filteredUsers[currentIndex].message_privacy === 0 && (
                        <button
                          className="swipe-btn"
                          onClick={handleMessageClick}
                        >
                          <FontAwesomeIcon icon={faMessage} />
                        </button>
                      )}
                      {/* WEITER BUTTON (Ohne Like) */}
                      <button onClick={nextProfile} className="swipe-btn">
                        ›
                      </button>
                    </div>
                  </div>
                );
              })()}
            </div>
          </div>
        )}
        {/* MODAL FÜR ERSTKONTAKT */}
        {messageModal && selectedUser && (
          <div className="message-init-modal modal">
            <div className="close-icon-div">
              <FontAwesomeIcon
                icon={faSquareXmark}
                className="icon close-icon"
                onClick={() => setMessageModal(false)}
              />
            </div>

            <div className="message-init-content">
              <h3>Nachricht an {selectedUser.name}</h3>
              <ul>
                <p>
                  <b>Tipps:</b>
                </p>
                <li>Nimm Dir Zeit für eine aussagekräftige erste Nachricht</li>
                <li>
                  Vermeide die Verhöhrsituation. Führe durch Deine Nachrichten
                  ein richtiges Gespräch und stelle Rückfragen, <br /> die über
                  das Profilhinausgehen
                </li>
                <li>
                  Ziel sollte ein <u>echtes Kennenlernen</u> sein. Nutze Deine
                  10 Nachrichten effektiv
                </li>
              </ul>

              <div className="init-msg-chat-div">
                <div className="init-chat-sub-div">
                  <textarea
                    placeholder="Schreib etwas Nettes..."
                    value={initMessage}
                    onChange={(e) => setInitMessage(e.target.value)}
                    rows={4}
                    className="init-message-textarea"
                  />
                  <div className="init-msg-button-div">
                    <button
                      className="send-init-btn icon-button"
                      onClick={startChat}
                      disabled={isSending}
                    >
                      {isSending ? (
                        "Wird gesendet..." //SPINNER
                      ) : (
                        <FontAwesomeIcon icon={faPaperPlane} className="icon" />
                      )}
                    </button>
                    <button onClick={showAiHelp} className="icon-button">
                      AI
                    </button>
                  </div>
                </div>
                {aiHelp && (
                  <div className="aihelp-content">
                    <div className="aihelp-info">
                      <div className="jerry-header">
                        <div className="aihelp-img">
                          <img
                            src="http://localhost/datingapp_ki/src/media/jerry.jpg"
                            alt="jerry"
                          />
                        </div>
                        <b>Jerry:</b>
                      </div>
                      <p>
                        {jerry}
                        <br />
                        {!loading && (
                          <div>
                            Wähle einen Stil:
                            <select
                              name="icebreaker_style"
                              id=""
                              value={aiHelpStyle} // Bindung an den State
                              onChange={(e) => setAiHelpStyle(e.target.value)}
                            >
                              <option value="funny">lustig</option>
                              <option value="respectful">höflich</option>
                              <option value="charming">charmant</option>
                              <option value="flirty">flirty</option>
                            </select>
                          </div>
                        )}
                      </p>
                    </div>
                    <button
                      onClick={aiHelpMessage}
                      className="icon-button"
                      disabled={loading}
                    >
                      Go
                    </button>
                  </div>
                )}
              </div>
            </div>
          </div>
        )}
        {likeAnimation && (
          <div className="animation-modal">
            <FontAwesomeIcon icon={faHeart} className="animation-object" />
          </div>
        )}
      </div>
    </div>
  );
}
