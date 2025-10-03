import Header from "@components/Header";
import MenuAside from "@components/MenuAside";
import Footer from "@components/Footer";
import MainSection from "@components/MainSection";
export default function Home() {

    return (
        <>
            <Header />
            <main className="min-h-[calc(100vh-10rem)] grid lg:grid-cols-5 sm:grid-cols-3 gap-4">
                <MenuAside />
                <MainSection
                    title="Bienvenue sur Playlisto"
                    description="Playlisto est votre assistant musical intelligent. Générez des playlists adaptées à votre humeur et à vos activités grâce à l’intelligence artificielle et à l’intégration avec Spotify."
                />
            </main>
            <Footer />
        </>
    );
}