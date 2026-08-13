import { useState, useEffect } from "react";
import { useLocation } from "react-router-dom";
import Footer from "./components/footer";
import Navbar from "./components/navbar";
import "./styles/request.scss";
import "./styles/main.scss";

const MAX_LENGTH = 500;

export default function Legal() {
  const { hash } = useLocation();

  useEffect(() => {
    if (hash) {
      // Entfernt das '#' und sucht das Element mit der passenden ID
      const element = document.getElementById(hash.replace("#", ""));
      if (element) {
        // Scrollt sanft oder direkt ('auto') zum Abschnitt
        element.scrollIntoView({ behavior: "smooth" });
      }
    }
  }, [hash]);

  return (
    <div className="body-div legal-content">
      <Navbar />
      <div className="legal-div">
        <h2 id="imprint">Impressum</h2>
        <p>
          Lorem ipsum dolor sit amet consectetur adipisicing elit. Totam
          inventore cumque aspernatur quibusdam autem error tempora explicabo
          dicta? Veritatis magnam libero exercitationem voluptatem quibusdam
          facilis incidunt nihil vero, reprehenderit itaque debitis eligendi
          cumque! Doloremque aliquam nulla qui, odit, itaque expedita ab
          blanditiis quia ullam, totam deleniti illo repudiandae! Minus suscipit
          fugiat, dolorum, nisi officiis nesciunt enim obcaecati hic sit
          voluptatem et. Molestiae voluptatem, esse quidem, itaque expedita
          dolorem libero minima eos hic quasi at illo iusto tempore blanditiis
          porro voluptate nihil. Velit tenetur quasi et, dolores fuga incidunt
          modi tempora eum tempore rem at, ut nemo cupiditate consequatur
          nostrum illum consectetur commodi ad recusandae. Vitae rem sint, quia
          exercitationem suscipit maxime. Consectetur natus minima at, iure
          ratione exercitationem provident excepturi repudiandae animi similique
          omnis temporibus nemo quidem tenetur veniam nulla adipisci, cum unde
          rerum dolore eius accusantium amet error officiis. Ipsum minus id
          nisi, iure harum quisquam in quas! Odio minus ad perspiciatis
          assumenda dolores modi, alias soluta quo! Saepe quasi eum in,
          provident hic aut ea magnam corrupti, ex quis voluptas esse sint
          obcaecati voluptatum laudantium, ipsum odio voluptatem itaque est!
          Exercitationem earum quas id fuga a quis ratione laboriosam quaerat at
          cumque quam, qui, placeat enim libero expedita est neque deserunt
          temporibus voluptatem cupiditate non excepturi. Fuga aspernatur dolore
          recusandae. Explicabo ipsam placeat rerum, neque eligendi distinctio
          iste mollitia blanditiis, aliquid sint voluptatem, earum minus aperiam
          labore veniam sunt dolores in quae dolor? Obcaecati, nostrum vero quas
          asperiores quam iusto consequatur ullam iste eos quia placeat sit
          officiis ipsum accusamus nemo quaerat iure odio magnam? Pariatur earum
          distinctio voluptas aliquam molestiae doloremque corrupti. Cum quae
          praesentium soluta! Reiciendis totam animi delectus quam nihil
          deleniti enim repudiandae et cumque obcaecati illum, labore tempora,
          voluptatem reprehenderit! Sint, iste? Quisquam nostrum temporibus quam
          quas quidem dolorem recusandae odio cum ipsam mollitia, doloremque
          excepturi blanditiis voluptates pariatur? Veritatis deserunt ducimus
          dolores voluptas pariatur suscipit eius voluptatum. Atque dignissimos
          distinctio et dolor rem at aspernatur, praesentium natus sit commodi
          delectus, aperiam, esse ab inventore quisquam eveniet molestias.
          Doloremque quidem modi nostrum facere vero laborum cum quisquam et
          iusto, molestiae quae veniam enim pariatur eius ad, dolore nulla
          expedita. Praesentium eos minus exercitationem autem totam architecto,
          blanditiis, atque, quod sapiente sint dolor voluptatem officia vel
          pariatur eveniet iste excepturi earum commodi voluptas unde amet
          adipisci! Nostrum rem illum dolor! Iure natus deleniti error omnis
          dignissimos magni inventore tempora quis reiciendis totam consequatur
          tenetur iusto placeat, dolorem repellat praesentium eveniet modi
          perferendis neque nisi aut nobis atque soluta fugit? Accusantium,
          distinctio quam dolorum tenetur voluptate rerum modi rem provident
          recusandae! Fugiat mollitia asperiores modi voluptatum. Est sequi
          alias dolore eligendi eum animi voluptatem quo quia molestiae corrupti
          culpa provident fuga, esse hic quaerat quis et excepturi temporibus
          ad? Omnis fugiat delectus ut. Quibusdam provident unde, expedita iure
          inventore dolorum reiciendis impedit aliquam hic vel est, repudiandae,
          obcaecati quam? Voluptatem, totam sequi nam aperiam quod odit, dolores
          labore iste fuga quibusdam iusto atque repellendus nemo cum dicta
          ducimus placeat ea suscipit dolorum ab praesentium minus! Aut incidunt
          deserunt quod tenetur dolorum minima a quaerat saepe cum quam nostrum,
          ipsam consequatur nisi voluptatum eligendi impedit delectus deleniti.
          Explicabo quibusdam autem, possimus qui doloremque nisi neque
          voluptatibus molestias est cum et culpa, similique esse officiis?
          Exercitationem suscipit soluta molestias similique impedit dicta minus
          voluptatum ad sequi blanditiis! Quo, aperiam excepturi. Porro rerum
          nesciunt repellat error voluptatibus blanditiis quidem deleniti
          accusantium nobis quos, nisi incidunt harum magnam, totam atque earum
          consequatur quod eaque fugit animi praesentium recusandae voluptates
          illo! Inventore nulla blanditiis sit magni explicabo, sed numquam
          doloribus, ab officia nam pariatur quasi! Animi repudiandae error
          praesentium vero cupiditate quasi temporibus expedita ab omnis
          assumenda. Quae quasi vero debitis animi culpa exercitationem
          consequuntur delectus architecto magni. Est adipisci autem blanditiis,
          quam fuga id accusamus ipsam assumenda sunt iusto facilis, laudantium
          natus sed veritatis deserunt totam necessitatibus eligendi nobis
          inventore neque, quaerat vero vitae architecto eos? Sequi tempora quae
          sint distinctio veritatis adipisci maiores quam. Numquam officiis
          suscipit quaerat ullam obcaecati similique deserunt. Doloremque
          obcaecati alias odit ipsum officiis rem quasi iure. Sint veniam dolore
          provident excepturi ex accusamus ullam voluptates quam labore ea,
          officiis vel placeat dolorem quod hic, nisi ducimus perferendis
          veritatis? Recusandae nulla quibusdam, provident hic rerum ad animi
          laborum eum non repellat sint eligendi ex libero error. Fugiat earum
          animi a dolorem suscipit atque! Facilis, fugiat dignissimos omnis odio
          deleniti, placeat esse est iusto vero quos illum tempora ducimus
          dolore obcaecati, fuga praesentium? Eius, unde neque? Ducimus vitae
          excepturi consequuntur quod ad quisquam impedit, in accusamus corrupti
          veniam, veritatis facere aspernatur id, esse explicabo quae molestiae
          aliquam quia laborum reiciendis molestias debitis! Error dolor ad
          nostrum tempore numquam pariatur, vitae aliquid quod libero? Quod
          praesentium, molestias, aspernatur quisquam odio quidem ex velit
          reiciendis non ratione repellendus minima iusto. Minima similique,
          porro recusandae minus alias neque non aut earum nihil perferendis ut
          nam autem ducimus possimus accusantium quae at illo ipsum beatae
          libero sint. Cumque maxime quaerat omnis perspiciatis inventore
          sapiente repellendus eius at culpa numquam, ipsum deleniti, unde
          expedita aliquam iusto sed dolore quae vel libero laudantium quasi
          commodi nostrum ullam. Velit consequuntur omnis impedit neque quaerat
          libero qui corrupti maiores reiciendis explicabo. Eius est quos modi
          officiis voluptatem facilis tempore, blanditiis maiores fugiat nemo
          praesentium minima labore aspernatur libero reprehenderit sit
          laboriosam illo excepturi! Quis, maiores! Ea deserunt, illum fugiat
          eos quasi nesciunt fugit sed blanditiis, repudiandae, enim doloremque
          accusamus a eligendi. Velit sed iste cupiditate ex nesciunt corrupti,
          hic, laboriosam optio fugit facere saepe, ipsum vel sequi. Culpa
          asperiores sequi esse cumque totam omnis voluptates assumenda, fugiat
          nesciunt vel dignissimos magnam! Fugit iure consequatur ab, numquam
          cum atque maiores eveniet, aliquam odit laboriosam ad illum suscipit,
          labore a quod accusamus corporis! Iusto mollitia alias, sapiente
          eveniet sequi voluptatem veniam nostrum ducimus dignissimos asperiores
          sunt quisquam ipsum sit beatae delectus officia placeat voluptates
          explicabo at in perspiciatis error. Aliquam quam quis necessitatibus
          odio accusamus ducimus totam dolore non exercitationem aperiam
          cupiditate itaque asperiores explicabo dignissimos illo porro dolores
          ex sequi, eos magnam vel maxime adipisci?
        </p>
      </div>
      <div className="legal-div">
        <h2 id="data-declaration">Datenschutz</h2>
        <p>
          Lorem ipsum, dolor sit amet consectetur adipisicing elit. Doloribus
          amet quas beatae officia numquam sit reiciendis, nesciunt architecto!
          Eaque laudantium, cum consequuntur perferendis veniam repellat tempore
          harum id alias, hic placeat. Impedit, tempore distinctio? Possimus
          numquam dignissimos culpa provident. Labore doloremque soluta eius
          esse alias, numquam consequatur nobis autem voluptas quia ducimus,
          libero consequuntur pariatur blanditiis quae molestiae impedit tempore
          nemo dicta iure veniam, recusandae ratione! Dignissimos aliquam
          doloribus repellendus, eaque voluptas cum dolores excepturi minus
          sequi adipisci dicta ratione ipsum quibusdam sed molestias
          consequuntur fugiat ex atque temporibus veniam. Neque, quaerat esse
          consequatur ipsum inventore nulla odio eius porro ipsa commodi. Modi,
          odit dolorum, consequuntur tempora enim, quia praesentium voluptate ab
          voluptatum harum ex sit corporis. Eos impedit provident aspernatur,
          inventore quos ipsum voluptate sint vero incidunt deserunt
          necessitatibus eum maiores pariatur iusto fugiat mollitia laboriosam!
          Reiciendis voluptate sunt temporibus? Minima doloribus optio eaque,
          reiciendis unde reprehenderit nemo, itaque magni quia, eum iste
          dolorem sit molestias odio quod! Similique delectus et voluptatum
          reiciendis nam earum, facilis vel, aliquam omnis, magni fuga quo
          inventore blanditiis. Numquam et incidunt blanditiis. Placeat corrupti
          repudiandae mollitia, doloribus maiores cumque inventore! Fuga,
          quibusdam a. Commodi nostrum atque nihil illum quidem magnam,
          voluptatibus libero molestiae eaque esse, minus laborum cupiditate.
          Amet itaque blanditiis minus distinctio unde, impedit, quas architecto
          dolorem provident aliquam ab molestias voluptatem accusamus id neque,
          voluptatibus inventore. Repellendus vero, et, debitis totam ipsum
          voluptate voluptates, fuga incidunt ex deleniti minus esse alias quae
          ab. Nemo, ratione similique. Tenetur veritatis sint nobis asperiores
          corporis sit quidem, ipsum magni fugiat, odit repellendus perferendis
          reiciendis blanditiis, iusto porro quaerat laborum dignissimos officia
          eius eveniet exercitationem ut vel. Et minus quis sed temporibus
          praesentium sequi laudantium architecto cumque voluptatum numquam ut
          sit placeat, suscipit adipisci mollitia, nesciunt aliquam at laborum
          earum nobis! Voluptatibus repellendus distinctio dicta, quam magnam ab
          quae iste voluptatem molestias itaque. Incidunt laboriosam soluta ad
          temporibus, quam atque. Distinctio commodi minus vero, pariatur quod
          provident, nihil amet, consequatur repudiandae nulla dolore voluptates
          reprehenderit. Error laboriosam impedit recusandae vel ex eum culpa
          distinctio aperiam quod nemo, ipsa suscipit quo ad magni, odio eius
          nostrum illo, temporibus nobis! Vitae quos reiciendis in, consectetur
          qui fuga veritatis impedit incidunt illo ullam doloremque? Sunt, minus
          dolore hic mollitia vitae velit similique dignissimos quis dolor
          fugiat beatae omnis molestiae accusantium voluptatem odit. Iusto,
          placeat! In magni consectetur atque molestiae laboriosam est.
          Repellendus at deleniti quasi quos corporis cupiditate similique sed
          accusantium sapiente. Sapiente magnam, distinctio asperiores nihil
          eligendi obcaecati omnis. Quas tenetur natus, quia culpa at ea aut
          enim nihil, laborum itaque dolorum quibusdam possimus laudantium
          pariatur voluptates cumque accusamus magni! Iusto placeat fugiat natus
          odit excepturi officia officiis laborum hic et autem dolore ad quis,
          dignissimos molestiae. Repellat expedita eaque fugiat perferendis
          cupiditate. Quaerat odio quisquam aut ratione minus itaque delectus
          veniam, in vitae sapiente animi at nam iure! Totam voluptatum sunt
          pariatur assumenda est obcaecati, quos, sapiente saepe autem
          laboriosam quasi quam, enim debitis eius eos. Minima, doloribus
          tempore distinctio velit id sunt odio ipsum in dignissimos, rem amet
          aut eos modi quae laboriosam ipsa unde ad, repellat quaerat optio iure
          quos dolorum. Nihil, numquam optio temporibus repellendus incidunt
          perferendis possimus. Explicabo eveniet obcaecati commodi blanditiis,
          autem, voluptates rerum quos reprehenderit repellendus earum ut ad
          ullam magni adipisci laborum. Autem soluta in obcaecati inventore.
          Culpa consequatur minima commodi libero tempore vel doloremque
          eligendi molestias expedita maiores amet autem laboriosam nostrum ex
          beatae voluptatibus quod quaerat soluta ab aperiam ipsam, voluptate
          accusantium? Culpa magnam veniam ad alias. Corporis mollitia iusto non
          consectetur quia earum explicabo obcaecati temporibus? Incidunt
          possimus eaque voluptatum ipsum consequuntur, aut nobis deleniti ex
          repudiandae ipsam quaerat earum alias beatae ab numquam error magnam
          repellendus corrupti! Veniam labore earum accusamus voluptatum
          excepturi praesentium molestiae cumque totam iusto enim dolore nostrum
          optio, beatae rerum deserunt minus fugit aliquid accusantium maxime
          illo nihil doloribus. Iste pariatur aliquam, quos eveniet rem natus
          facere iusto, amet ullam assumenda illum, architecto et ea? Hic esse
          corrupti ratione autem officia eum quos, delectus, voluptate fugit
          nemo odit nobis at cumque sed illum vero tenetur tempore, molestias
          earum error laborum obcaecati. Explicabo minus quae quidem quo omnis
          accusantium, impedit quas labore a temporibus ipsa ipsum quisquam
          libero at, sed vero veniam nobis maxime cumque amet! Omnis aperiam
          facere aliquam tenetur velit excepturi reprehenderit doloribus
          corporis fugiat quasi? Sunt atque voluptatibus accusamus repellendus
          et dignissimos, neque delectus natus dolor autem minima officia
          aliquid fugiat distinctio reiciendis explicabo nobis at quo. Harum
          perspiciatis quis repudiandae iste esse rerum optio exercitationem
          nihil? Ipsum suscipit officiis eveniet, eos pariatur enim officia
          laudantium accusantium veniam harum! Minima quasi, non voluptas
          aspernatur error nihil quos consequuntur exercitationem dolor aut
          odio. Quis voluptates deserunt eius, nulla architecto sint iste natus
          eaque possimus quos hic ad illo corrupti quo! Necessitatibus natus
          itaque at voluptate nam ut quisquam dolorem rerum. Saepe debitis
          suscipit tempore maiores commodi repellendus eum eius accusamus, nulla
          ab sunt obcaecati, nisi illo hic dignissimos. Reprehenderit vitae
          earum omnis eligendi molestias placeat tempora exercitationem cumque
          amet repudiandae modi dignissimos perferendis optio quo veniam, neque
          dolores pariatur sit, blanditiis odio impedit ab velit vero et?
          Similique laboriosam maxime quae alias necessitatibus laborum! Iure
          atque corrupti voluptatum laborum natus qui nisi omnis quaerat enim
          dolorum nobis ipsum quam ut ullam pariatur dignissimos repellendus
          explicabo unde aliquam cumque, rem quas. Iusto, dignissimos. Iste sunt
          saepe odit culpa alias, porro numquam qui obcaecati consequatur
          distinctio eaque corporis ab, doloremque libero? Eum facere aperiam
          necessitatibus sequi dignissimos, nam illo illum enim earum voluptatem
          quod aut repudiandae inventore magni iusto quasi cumque cum molestias
          aspernatur temporibus quae consequuntur possimus vel sit! Animi eum
          quasi similique ad impedit! Consectetur laudantium excepturi, facere
          facilis ipsa debitis dolorum nulla eaque libero adipisci nostrum,
          dignissimos repellat ducimus, ullam reprehenderit. Maxime voluptates
          accusantium provident. Eos voluptates ipsum laboriosam obcaecati
          molestias! Fugit alias, distinctio illo cumque nisi est consequuntur
          labore ipsum itaque sunt, dolores temporibus ipsam blanditiis ut
          tempora. Ratione ducimus doloribus numquam perferendis similique
          velit? Nemo repudiandae repellat molestias corrupti rem quia debitis a
          nostrum? Ipsum, similique consectetur.
        </p>
      </div>
      <div className="legal-div">
        <h2 id="terms-conditions">Allgemeine Geschäftsbedingungen (AGB)</h2>
        <p>
          Lorem ipsum, dolor sit amet consectetur adipisicing elit. Veniam
          itaque voluptates animi placeat quam sapiente autem vitae facilis
          provident, architecto laudantium iusto neque corrupti numquam
          voluptatum sint officiis accusamus at aspernatur dicta expedita est
          voluptas distinctio! Quas ullam earum labore odit sequi? Cupiditate
          nisi repellendus quibusdam. Suscipit nostrum molestias, reiciendis hic
          nesciunt, recusandae sunt quas, vitae eum illum vero nobis!
          Praesentium atque quos architecto assumenda repellendus amet placeat
          dignissimos adipisci magni mollitia eius harum ut reprehenderit
          aliquam, quaerat cupiditate. Reiciendis accusantium totam, adipisci
          dolorem facere earum doloremque dignissimos, laboriosam ex quasi sunt
          vel? Omnis aliquam sequi vel assumenda corporis voluptas ab voluptatum
          quaerat modi debitis consequuntur, ut praesentium id, error quibusdam
          earum placeat! Aspernatur facere voluptatum labore vero distinctio
          atque provident, numquam dicta perferendis hic repellat consectetur
          adipisci ut a est porro at fuga debitis praesentium, explicabo autem?
          Amet, quod suscipit itaque, neque fugit ducimus, nisi sint quae dolore
          quas maiores expedita autem assumenda rerum perspiciatis ipsum
          provident porro vero? Nisi sapiente quam beatae illo ea temporibus
          cumque illum laboriosam? Tempora corporis provident dignissimos,
          voluptas, molestiae, molestias saepe earum nesciunt id ex aut harum
          eum amet perspiciatis minus beatae! Rerum minima sint tempore
          suscipit, quibusdam fuga eius mollitia sunt ratione laborum in
          temporibus cum corporis blanditiis id, beatae aut, enim repudiandae
          quas ullam vero. Accusantium autem cupiditate animi odio commodi
          incidunt aperiam deserunt recusandae sunt dolor ipsa eligendi,
          perspiciatis nostrum ex consequatur quae minus neque in maxime rem
          impedit? Aperiam perspiciatis nam sint vitae magnam ut qui
          necessitatibus explicabo officiis iusto voluptate ratione eius tempore
          corrupti nihil inventore, possimus quibusdam odit consequatur fugiat,
          impedit maxime cum repudiandae rem? Sapiente doloribus rerum voluptate
          numquam quasi natus maiores libero, voluptatum distinctio esse
          molestiae ipsam nemo aliquid veritatis! Sunt quibusdam maiores iure
          voluptatum delectus ab itaque labore magnam nobis nostrum quo
          recusandae illum aspernatur placeat, quia excepturi quod doloremque
          quasi reiciendis alias incidunt! Porro rerum repudiandae dolorem sunt
          quas illo, omnis, eaque aperiam quis iste deleniti. Nihil consequuntur
          commodi ut tempora optio sed eligendi voluptatibus cumque voluptatem.
          Dolorum voluptatum consectetur fuga eos aliquid provident id sed
          nostrum cupiditate expedita quia dicta perspiciatis, amet asperiores
          recusandae exercitationem in fugiat, vitae quasi, mollitia minima!
          Assumenda labore earum aliquid inventore magnam quia minima rem
          voluptate! Reprehenderit ducimus inventore voluptatem sit, rem
          possimus? Vero quo nobis dolore officia perferendis eaque praesentium
          totam nemo nam, velit quibusdam molestiae id similique dignissimos
          consectetur nulla alias ipsum, nisi pariatur voluptatem quaerat
          debitis corporis. Veritatis, autem deserunt alias quasi laboriosam
          ducimus? Esse nam pariatur amet, eius tempore unde laudantium ea
          perferendis, quibusdam animi doloremque maxime sint earum? Laborum at
          commodi fugit ipsa quos dignissimos enim expedita provident sunt
          excepturi sapiente, minus delectus harum in! Maxime distinctio aperiam
          culpa deserunt laborum iste nihil nam at voluptatibus modi ab quos
          minus, ullam ex recusandae voluptatem necessitatibus reiciendis vel
          placeat eligendi expedita, non ipsa. Voluptatibus reprehenderit
          doloremque nostrum placeat? Quod quisquam enim corrupti! Quas cum
          reprehenderit impedit eum accusamus, ratione quidem iure inventore
          magni quaerat provident officia odio soluta molestiae expedita at id
          itaque illum quos nobis harum officiis necessitatibus assumenda aut.
          Sequi obcaecati saepe eveniet reprehenderit itaque exercitationem,
          illo a doloribus nisi ut iste recusandae velit. Non incidunt
          consequuntur aliquam doloribus ducimus error dolorum. Repellendus
          perspiciatis quasi molestiae quis corporis corrupti necessitatibus
          natus dolore, consectetur error consequatur quod velit possimus
          veritatis expedita rerum, vel rem nam quae ad suscipit. Odio, laborum
          omnis at veniam nisi consectetur quia voluptatum, quisquam quod
          nostrum, possimus eum temporibus odit incidunt inventore. Nisi,
          tenetur ex possimus eveniet, amet error, dolor incidunt eaque non
          accusantium aut maxime enim veniam neque iure ea nulla. Magnam in
          perspiciatis rerum nostrum fugiat corrupti alias consequatur
          exercitationem vel ratione dolore cum sit quod aliquid iure expedita,
          modi quidem! Eaque dolores ducimus, dolorem laboriosam vel omnis quam,
          adipisci illum, a explicabo voluptate quas minus porro distinctio
          nobis esse voluptatem odio dolorum deleniti totam sint tenetur.
          Facilis dolorem molestiae velit officia laborum dicta a aspernatur
          cupiditate error obcaecati, nihil culpa aliquid esse sit? Dolorem sit
          quo fugiat obcaecati inventore. Saepe molestiae temporibus itaque
          vitae, doloribus quos iure cupiditate vero eius natus rerum corrupti
          mollitia et esse rem possimus totam numquam, recusandae ipsa earum
          consequatur ab amet? Totam corporis modi recusandae iusto laboriosam
          quaerat vel maiores eveniet repudiandae dolore itaque illum quibusdam,
          atque at iste assumenda sunt eum quidem error maxime rerum impedit
          consequuntur quae? Blanditiis esse similique nobis repudiandae cumque,
          laudantium velit quo harum laboriosam delectus deserunt, officiis, ut
          neque placeat asperiores. Fuga repellendus voluptatem provident, esse
          quae velit quisquam molestiae laboriosam illum distinctio amet
          suscipit corrupti dolores ullam consequuntur asperiores nisi nobis eos
          aliquid sapiente ipsum ad et. Ad natus reiciendis est recusandae?
          Error dolores aliquam natus eaque quam, quae iure nemo tenetur commodi
          tempora quis ipsum iusto temporibus possimus, labore earum maiores a
          facilis? Odit libero cupiditate mollitia, eligendi voluptatem ut
          inventore et. Obcaecati, eos? Consequatur officia quisquam, saepe
          necessitatibus, officiis maxime ex dolor quia aperiam voluptatibus
          voluptas unde cupiditate suscipit autem optio cumque, magnam explicabo
          sunt expedita iusto eaque amet facilis. Iusto officiis impedit nobis
          fugit asperiores quas, minus nam delectus, deserunt ex excepturi.
          Reiciendis voluptatum dolorem culpa tempora dolores perferendis qui a
          unde itaque, ipsam facilis error corrupti sed nobis, adipisci atque.
          Deleniti obcaecati ipsam similique alias ab atque facilis reiciendis
          ex sunt accusantium, odio architecto possimus quis id perferendis
          sequi quaerat dignissimos labore deserunt? Tenetur numquam quaerat
          molestias, amet exercitationem explicabo ullam debitis qui harum.
          Debitis ut sit similique iusto aliquam, rerum odit, a distinctio earum
          veritatis voluptatibus officia cum numquam sapiente quis quia aliquid
          itaque fuga cumque recusandae omnis explicabo. Temporibus esse fugit
          vel molestias nisi, accusamus odit animi autem dolorum dignissimos
          distinctio exercitationem provident incidunt, recusandae nostrum
          doloremque voluptatum sequi reiciendis! Et, eius veniam doloremque
          maiores officia consectetur! Consequatur, dolor earum modi quos
          repellendus explicabo possimus at eos sapiente cumque accusantium
          fugiat iure libero et excepturi laboriosam harum nemo? Id quisquam,
          ipsam dolores ducimus accusamus minus perferendis reiciendis. Id
          vitae, quaerat cumque fuga perferendis consequuntur, adipisci ex nulla
          voluptatem eum sunt molestiae dolorem ipsum sapiente mollitia et
          quidem?
        </p>
      </div>
      <Footer />
    </div>
  );
}
