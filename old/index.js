import puppeteer from "puppeteer";

(async () => {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();

  await page.goto("http://127.0.0.1:5500/resume.html", {
    waitUntil: "networkidle0"
  });

  await page.pdf({
    path: "cv.pdf",
    format: "A4",
    printBackground: true
  });

  await browser.close();
})();
