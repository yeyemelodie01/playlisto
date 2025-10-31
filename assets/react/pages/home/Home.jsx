import Header from "@components/Header";
import MenuAside from "@components/MenuAside";
import Footer from "@components/Footer";
import MainSection from "@components/MainSection";
export default function Home() {

  return (
    <>
      <Header />
      <main className="h-[49.3rem] flex items-center justify-center p-4 sm:p-8 overflow-hidden">
        <section className="text-center max-w-2xl">
          <h1 className="text-3xl font-bold mb-4">Bienvenue sur Playlisto</h1>
          <p className="text-lg">
            Playlisto est votre assistant musical intelligent. Générez des playlists adaptées à votre humeur et à vos activités grâce à l’intelligence artificielle et à l’intégration avec Spotify.
          </p>
          <button className="btn btn-primary mt-6">Générer une playlist</button>
        </section>
      </main>
    </>
  );
}