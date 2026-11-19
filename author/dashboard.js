const sampleSubmissions = [
  {
    title: "The role of open access in modern scholarship",
    abstract: "This abstract summarizes the manuscript and its contribution to current scholarly publishing practice.",
    file: "open-access-scholarship.docx",
    date: "16 Nov 2026",
    status: "Pending Review",
    statusNote: "Your manuscript is being reviewed.",
    decisionDate: "Not yet available",
    statusClass: "status-pending",
  },
  { title: "Editorial workflows for emerging journals", date: "08 Nov 2026", status: "Under Review", statusClass: "status-review" },
  { title: "A framework for sustainable publishing", date: "27 Oct 2026", status: "Accepted", statusClass: "status-accepted" },
  { title: "Digital archives and scholarly access", date: "14 Oct 2026", status: "Rejected", statusClass: "status-rejected" },
  { title: "Peer review transparency in practice", date: "02 Oct 2026", status: "Pending Review", statusClass: "status-pending" },
];

// Demo rows are kept out of the default view. Replace this source with the
// authenticated author's API response when the backend is connected.
const isDemoView = new URLSearchParams(window.location.search).has("demo");
const submissions = isDemoView ? sampleSubmissions : [];
const tableScroll = document.querySelector(".table-scroll");
const emptyState = document.querySelector(".empty-state");
const submissionRows = document.querySelector("#submission-rows");
const detailsDialog = document.querySelector(".submission-dialog");
const dialogTitle = document.querySelector("#dialog-title");
const dialogDate = document.querySelector("#dialog-date");
const dialogStatus = document.querySelector("#dialog-status");
const dialogAbstract = document.querySelector("#dialog-abstract");
const dialogFile = document.querySelector("#dialog-file");
const dialogStatusNote = document.querySelector("#dialog-status-note");
const dialogDecisionDate = document.querySelector("#dialog-decision-date");

function addCell(row, text, tagName = "td", className = "") {
  const cell = document.createElement(tagName);
  cell.textContent = text;
  if (className) cell.className = className;
  row.append(cell);
  return cell;
}

submissions.forEach((submission, index) => {
  const row = document.createElement("tr");
  addCell(row, submission.title, "th");
  row.lastElementChild.scope = "row";
  addCell(row, submission.date);

  const statusCell = row.insertCell();
  const status = document.createElement("span");
  status.className = `status ${submission.statusClass}`;
  status.textContent = submission.status;
  statusCell.append(status);

  const actionCell = row.insertCell();
  const detailsButton = document.createElement("button");
  detailsButton.className = "details-button";
  detailsButton.type = "button";
  detailsButton.dataset.submission = String(index);
  detailsButton.textContent = "View Details";
  actionCell.append(detailsButton);
  submissionRows.append(row);
});

const isEmpty = submissions.length === 0;
document.body.classList.toggle("is-empty", isEmpty);
tableScroll.hidden = isEmpty;
emptyState.hidden = !isEmpty;

submissionRows.addEventListener("click", (event) => {
  const button = event.target.closest("[data-submission]");
  if (!button) return;

  const submission = submissions[Number(button.dataset.submission)];
  if (!submission) return;

  dialogTitle.textContent = submission.title;
  dialogAbstract.textContent = submission.abstract || "Details are not available for this sample submission.";
  dialogFile.textContent = submission.file || "Not available";
  dialogDate.textContent = submission.date;
  dialogStatus.textContent = submission.status;
  dialogStatusNote.textContent = submission.statusNote || "";
  dialogDecisionDate.textContent = submission.decisionDate || "Not available";
  detailsDialog.showModal();
});

document.querySelectorAll(".dialog-close, .dialog-bottom-close").forEach((button) => {
  button.addEventListener("click", () => detailsDialog.close());
});

detailsDialog.addEventListener("click", (event) => {
  if (event.target === detailsDialog) detailsDialog.close();
});
